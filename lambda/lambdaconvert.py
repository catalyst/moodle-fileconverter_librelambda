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
import urllib.request
import subprocess

s3_client = boto3.client('s3')

logger = logging.getLogger()
logger.setLevel(logging.INFO)


def get_libreoffice(downloadurl):
    """
    This method downloads and extracts the Libre Office tar archive and extracts
    it locally for the lambda function to use.
    As this only needs to happen on Lmabda 'cold starts' it first checks if Libre Office
    is already available.
    """

    # Only get Libre Office if this is a cold start and we don't arleady have it.
    if not os.path.exists('/tmp/instdir/program/soffice'):
        logger.info('Downloading and extracting Libre Office')
        with urllib.request.urlopen(downloadurl) as response:
            with tarfile.open(fileobj=response, mode="r|xz") as archive:
                archive.extractall('/tmp')  # Extract to the temp directory of Lambda.
    else:
        logger.info('Libre Office executable exists already.')


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

    #  Get and unpack the Libre Office package.
    libreurl = os.environ['LibreLocation']
    get_libreoffice(libreurl)

    #  Now get and process the file from the input bucket.
    for record in event['Records']:
        bucket = record['s3']['bucket']['name']
        key = record['s3']['object']['key']
        response = s3_client.head_object(Bucket=bucket, Key=key)

        targetformat = response['Metadata']['targetformat']
        conversionid = response['Metadata']['id']
        sourcefileid = response['Metadata']['sourcefileid']

        download_path = '/tmp/{}{}'.format(uuid.uuid4(), key)
        upload_path = '{}.pdf'.format(download_path)  # Conversion appends .pdf extension to file.

        # Download the input file from S3
        s3_client.download_file(bucket, key, download_path)

        # Convert the input file.
        convert_file(download_path, targetformat)

        # Upload the converted file to the output S3 as the original name.
        metadata = {"Metadata": {"id": conversionid, "sourcefileid": sourcefileid}}
        s3_client.upload_file(upload_path, os.environ['OutputBucket'], key, ExtraArgs=metadata)

        # Remove file from input bucket
        s3_client.delete_object(Bucket=bucket, Key=key)

        # Remove files from temp
        os.remove(download_path)
        os.remove(upload_path)
