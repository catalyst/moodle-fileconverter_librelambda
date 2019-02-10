import boto3
import botocore
import json
import zipfile
import tempfile
import os
import mimetypes
import logging
import uuid
import tarfile
import io
import subprocess
from botocore.exceptions import ClientError
from multiprocessing import Process, Pipe

s3_client = boto3.client('s3')
logger = logging.getLogger()


def get_libreoffice(librearchive, resourcebucket):
    """
    This method downloads and extracts the Libre Office tar archive and extracts
    it locally for the lambda function to use.
    As this only needs to happen on Lmabda 'cold starts' it first checks if Libre Office
    is already available.
    """

    # Only get Libre Office if this is a cold start and we don't arleady have it.
    if not os.path.exists('/tmp/instdir/program/soffice'):
        logger.info('Downloading and extracting Libre Office')
        s3Obj = s3_client.get_object(
            Bucket=resourcebucket,
            Key=librearchive)
        with tarfile.open(fileobj=s3Obj['Body'], mode="r|xz") as archive:
            archive.extractall('/tmp')  # Extract to the temp directory of Lambda.

    else:
        logger.info('Libre Office executable exists already.')


def get_object_data(record, bucket, key):
    """
    Given an event record get object data that will be used in
    the object processing.
    """

    logger.info("Processing object with key {}".format(key))

    #  Get object data.
    try:
        response = s3_client.head_object(Bucket=bucket, Key=key)
    except ClientError as e:
        code = e.response['Error']['Code']
        message = e.response['Error']['Message']
        logger.error("Head object error, code: {} Error Message: {}".format(code, message))
        response = False

    if response:
        targetformat = response['Metadata']['targetformat']
        conversionid = response['Metadata']['id']
        sourcefileid = response['Metadata']['sourcefileid']
    else:
        targetformat = False
        conversionid = False
        sourcefileid = False

    return (targetformat, conversionid, sourcefileid)


def convert_file(filepath, targetformat):
    """
    Convert the input file to PDF.
    Return the path of the converted document/
    """
    commandargs = [
        "/tmp/instdir/program/soffice",  # Libre conversion executable.
        "--headless",
        "--invisible",
        "--nodefault",
        "--nofirststartwizard",
        "--nolockcheck",
        "--nologo",
        "--norestore",
        "--convert-to",
        targetformat,
        "--outdir",
        "/tmp",
        filepath  # Needs to be the absolute path as a string
        ]

    result = subprocess.run(commandargs, stdout=subprocess.PIPE)
    result.check_returncode()  # Throw an error on non zero return code.
    #  TODO: add some logging an error handling.


def lambda_handler(event, context):
    """
    lambda_handler is the entry point that is invoked when the lambda function is called,
    more information can be found in the docs:
    https://docs.aws.amazon.com/lambda/latest/dg/python-programming-model-handler-types.html

    Get the input document from the input S3 bucket. Convert the input file into the desired format.
    Upload the converted document to the output S3 bucket.
    """

    #  Set logging
    logging_level = os.environ.get('LoggingLevel', logging.ERROR)
    logger.setLevel(int(logging_level))

    #  Get and unpack the Libre Office package.
    librearchive = os.environ['LibreLocation']
    resourcebucket = os.environ['ResourceBucket']

    #  Now get and process the file from the input bucket.
    for record in event['Records']:
        bucket = record['s3']['bucket']['name']
        key = record['s3']['object']['key']

        #  Filter out permissions check file.
        #  This is initiated by Moodle to check bucket access is correct
        if key == 'permissions_check_file':
            continue

        #  Get object data.
        targetformat, conversionid, sourcefileid = get_object_data(record, bucket, key)
        if not targetformat:
            continue  #  Break this loop cycle if we can't head object.

        download_path = '/tmp/{}{}'.format(uuid.uuid4(), key)
        upload_path = '{}.pdf'.format(download_path)  # Conversion appends .pdf extension to file.

        # First multiprocessing split.
        # Download LibreOffice and input bucket object.
        processes = []  # create a list to keep all processes
        parent_connections = []  # create a list to keep connections

        # Download LibreOffice
        parent_conn, child_conn = Pipe()  # Create a pipe for communication.
        parent_connections.append(parent_conn)
        process = Process(target=get_libreoffice, args=(librearchive, resourcebucket,))
        processes.append(process)

        # Download the input file from S3
        parent_conn, child_conn = Pipe()  # Create a pipe for communication.
        parent_connections.append(parent_conn)
        process = Process(target=s3_client.download_file, args=(bucket, key, download_path,))
        processes.append(process)

        # Start all processes.
        for process in processes:
            process.start()

        # Make sure that all processes have finished.
        for process in processes:
            process.join()

        # Second multiprocessing split.
        # Convert file and remove original from input bucket.
        processes = []  # create a list to keep all processes
        parent_connections = []  # create a list to keep connections

        # Convert the input file.
        parent_conn, child_conn = Pipe()  # Create a pipe for communication.
        parent_connections.append(parent_conn)
        process = Process(target=convert_file, args=(download_path, targetformat,))
        processes.append(process)

        # Remove file from input bucket
        parent_conn, child_conn = Pipe()  # Create a pipe for communication.
        parent_connections.append(parent_conn)
        process = Process(target=s3_client.delete_object, kwargs={'Bucket':bucket, 'Key':key})
        processes.append(process)

         # Start all processes.
        for process in processes:
            process.start()

        # Make sure that all processes have finished.
        for process in processes:
            process.join()

        # Third multiprocessing split.
        # Upload converted file to output bucket and delete local input.

        # Delete local output file.

        # Upload the converted file to the output S3 as the original name.
        metadata = {"Metadata": {"id": conversionid, "sourcefileid": sourcefileid}}
        s3_client.upload_file(upload_path, os.environ['OutputBucket'], key, ExtraArgs=metadata)

        # Remove files from temp
        os.remove(download_path)
        os.remove(upload_path)
