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
import urllib
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
    if not os.path.exists('/tmp/instdir'):
        file_tmp = urllib.urlretrieve(downloadurl, filename=None)[0]  # Download file to Python temp.
        tar = tarfile.open(file_tmp)S
        tar.extractall('/tmp')  # Extract to the temp directory of Lambda.
        
def convert_file(filepath):
    """
    Convert the input file to PDF.
    Return the path of the converted document/
    """
    
    #./instdir/program/soffice --headless --invisible --nodefault --nofirststartwizard --nolockcheck --nologo --norestore --convert-to pdf --outdir /tmp /var/www/moodle/files/converter/librelambda/tests/fixtures/testsubmission.odt
    
    subprocess.run(["ls", "-l", "/dev/null"], capture_output=True)

dev save_output(filepath):
    """
    Save the converted file to the output S3 bucket.
    """


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

        logger.info('Response: {}'.format(response))

        targetformat = response['Metadata']['targetformat']
        conversionid = response['Metadata']['id']
        sourcefileid = response['Metadata']['sourcefileid']

        download_path = '/tmp/{}{}'.format(uuid.uuid4(), key)
        # upload_path = '/tmp/resized-{}'.format(key)
        upload_path = download_path

        s3_client.download_file(bucket, key, download_path)

        # TODO: pass the output bucket name in at runtime.
        s3_client.upload_file(upload_path, os.environ['OutputBucket'], key)
