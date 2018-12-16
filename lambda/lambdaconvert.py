import boto3
import botocore
import json
import zipfile
import tempfile
import os
import mimetypes
import logging

s3_client = boto3.client('s3')

logger = logging.getLogger()
logger.setLevel(logging.INFO)


def lambda_handler(event, context):
    """
    lambda_handler is the entry point that is invoked when the lambda function is called,
    more information can be found in the docs:
    https://docs.aws.amazon.com/lambda/latest/dg/python-programming-model-handler-types.html

    Get the input document from the input S3 bucket. Convert the input file into the desired format.
    Upload the converted document to the output S3 bucket.
    """
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
        s3_client.upload_file(upload_path, 'librelamdamoodle-output', key)
