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


def get_libreoffice():
    """
    This method downloads and extracts the Libre Office tar archive and extracts
    it locally for the lambda function to use.
    As this only needs to happen on Lmabda 'cold starts' it first checks if Libre Office
    is already available.
    """

    # Only get Libre Office if this is a cold start and we don't arleady have it.
    if not os.path.exists('/tmp/instdir/program/soffice'):
        logger.info('Downloading and extracting Libre Office')
        with tarfile.open(name='/opt/lo.tar.xz', mode="r|xz") as archive:
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


def action_multiprocessing(multiprocesses):
    """
    Process multiple actions at once.
    This is just a thin wrapper around multiprocessing.Process. Lambda has a limited
    environment so we can only use select multiprocessing tools. We pass in a list of dictionaries
    of the methods we want to run along with their args and kwargs and this method will "queue" them
    up and then execute them.
    """
    processes = []  # create a list to keep all processes
    parent_connections = []  # create a list to keep connections

    for multiprocess in multiprocesses:
        parent_conn, child_conn = Pipe()  # Create a pipe for communication.
        parent_connections.append(parent_conn)
        process = Process(target=multiprocess['method'], args=multiprocess['processargs'], kwargs=multiprocess['proxesskwargs'])
        processes.append(process)

    # Start all processes.
    for process in processes:
        process.start()

    # Make sure that all processes have finished.
    for process in processes:
        process.join()


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
        multiprocesses = (
            {
            'method' : get_libreoffice,
            'processargs': (),
            'proxesskwargs': {}
            },
            {
            'method' : s3_client.download_file,
            'processargs': (bucket, key, download_path,),
            'proxesskwargs': {}
            },
        )
        action_multiprocessing(multiprocesses)

        # Second multiprocessing split.
        # Convert file and remove original from input bucket.
        multiprocesses = (
            {
            'method' : convert_file,
            'processargs': (download_path, targetformat,),
            'proxesskwargs': {}
            },
            {
            'method' : s3_client.delete_object,
            'processargs': (),
            'proxesskwargs': {'Bucket':bucket, 'Key':key}
            },
        )
        action_multiprocessing(multiprocesses)

        # Third multiprocessing split.
        # Upload converted file to output bucket and delete local input.
        metadata = {"Metadata": {"id": conversionid, "sourcefileid": sourcefileid}}
        multiprocesses = (
            {
            'method' : s3_client.upload_file,
            'processargs': (upload_path, os.environ['OutputBucket'], key,),
            'proxesskwargs': {'ExtraArgs': metadata}
            },
            {
            'method' : os.remove,
            'processargs': (download_path,),
            'proxesskwargs': {}
            },
        )
        action_multiprocessing(multiprocesses)

        # Delete local output file.
        os.remove(upload_path)
