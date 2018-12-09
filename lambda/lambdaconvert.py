import boto3
import os
import sys
import uuid

s3_client = boto3.client('s3')


def handler(event, context):
    for record in event['Records']:
        bucket = record['s3']['bucket']['name']
        key = record['s3']['object']['key']
        download_path = '/tmp/{}{}'.format(uuid.uuid4(), key)
        upload_path = '/tmp/resized-{}'.format(key)

        s3_client.download_file(bucket, key, download_path)

        s3_client.upload_file(upload_path, '{}resized'.format(bucket), key)
