'''
This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.

@copyright   2019 Matt Porritt <mattp@catalyst-au.net>
@license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

'''

import boto3
import os
import logging
import uuid
import tarfile
import subprocess
import inspect
from multiprocessing import Process, Pipe

s3_client = boto3.client('s3')
logger = logging.getLogger()

INSTDIR = '/tmp/instdir'
SOFFICE = INSTDIR + '/program/soffice.bin'  # Libre conversion executable.
CONFDIR = INSTDIR + '/user/config'
FONTDIR = INSTDIR + '/share/fonts'

fonts_conf = inspect.cleandoc(f'''
<?xml version="1.0"?>
<!DOCTYPE fontconfig SYSTEM "fonts.dtd">
<fontconfig>
  <dir>{FONTDIR}</dir>
  <!-- This may or not require font family specs.
       Currently the only purpose is to stop fontconfig complaining. -->
</fontconfig>
''')

keep_files = not os.environ.get('KEEP_FILES', '') == ''


def get_libreoffice():
    """
    This method downloads and extracts the Libre Office tar archive and extracts
    it locally for the lambda function to use.
    As this only needs to happen on Lambda 'cold starts' it first checks if Libre Office
    is already available.
    """

    # Only get Libre Office if this is a cold start and we don't arleady have it.
    if not os.path.exists(SOFFICE):
        logger.info('Downloading and extracting Libre Office')
        with tarfile.open(name='/opt/lo.tar.xz', mode="r|xz") as archive:
            archive.extractall('/tmp')  # Extract to the temp directory of Lambda.
        with open(CONFDIR + '/fonts.conf', 'w') as fc:
            fc.write(fonts_conf)

    else:
        logger.info('Libre Office executable exists already.')


def get_object_data(bucket, key):
    """
    Get object data that will be used in the object processing.
    """

    logger.info("Processing object with key {}".format(key))

    response = s3_client.head_object(Bucket=bucket, Key=key)

    metadata = response.get('Metadata')
    if not metadata:
        raise RuntimeError("Head response object without Metadata", response)

    targetformat = metadata['targetformat']
    conversionid = metadata['id']
    sourcefileid = metadata['sourcefileid']

    return (targetformat, conversionid, sourcefileid)


def convert_file(filepath, targetformat):
    """
    Convert the input file to PDF.
    Return the path of the converted document/
    """
    commandargs = [
        SOFFICE,
        "--headless",
        "--invisible",
        "--nodefault",
        "--nofirststartwizard",
        "--nolockcheck",
        "--nologo",
        "--norestore",
        "--writer",
        "--convert-to",
        targetformat,
        "--outdir",
        "/tmp",
        filepath  # Needs to be the absolute path as a string
        ]

    env = os.environ.copy()
    env['FONTCONFIG_PATH'] = CONFDIR
    subprocess.run(commandargs, env=env, timeout=300, check=True)
    #  TODO: add some logging an error handling.


def action_multiprocessing(multiprocesses):
    """
    Process multiple actions at once.
    This is just a thin wrapper around multiprocessing.Process.
    Lambda has a limited environment so we can only use select multiprocessing tools.
    We pass in a list of dictionaries of the methods we want to run along with their
    args and kwargs and this method will "queue" them up and then execute them.
    """
    processes = []  # create a list to keep all processes
    parent_connections = []  # create a list to keep connections

    for multiprocess in multiprocesses:
        parent_conn, child_conn = Pipe()  # Create a pipe for communication.
        parent_connections.append(parent_conn)
        process = Process(
            target=multiprocess['method'],
            args=multiprocess['processargs'],
            kwargs=multiprocess['processkwargs'],
        )
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

    Get the input document from the input S3 bucket.
    Convert the input file into the desired format.
    Upload the converted document to the output S3 bucket.
    """

    #  Set logging
    logging_level = os.environ.get('LoggingLevel', logging.ERROR)
    logger.setLevel(int(logging_level))

    get_libreoffice()

    last_exception = None
    try:
        for record in event['Records']:
            try:
                process(record)
            except Exception as e:
                logger.exception("%r", record)
                last_exception = e
    except Exception:
        logger.exception("%r %r", event, context)
        raise

    if last_exception:
        raise last_exception


def process(record):
    """
    Get and process the file from the input bucket.
    """

    bucket = record['s3']['bucket']['name']
    key = record['s3']['object']['key']

    #  Filter out permissions check file.
    #  This is initiated by Moodle to check bucket access is correct
    if key == 'permissions_check_file':
        return

    #  Get object data.
    targetformat, conversionid, sourcefileid = get_object_data(bucket, key)

    download_path = '/tmp/{}{}'.format(uuid.uuid4(), key)
    # Conversion appends .pdf extension to file.
    upload_path = '{}.pdf'.format(download_path)

    # First multiprocessing split.
    # Download LibreOffice and input bucket object.
    multiprocesses = (
        {
            'method': s3_client.download_file,
            'processargs': (bucket, key, download_path,),
            'processkwargs': {}
        },
    )
    action_multiprocessing(multiprocesses)

    # Second multiprocessing split.
    # Convert file and remove original from input bucket.
    multiprocesses = (
        {
            'method': convert_file,
            'processargs': (download_path, targetformat,),
            'processkwargs': {}
        },
    )
    if not keep_files:
        multiprocesses += (
            {
                'method': s3_client.delete_object,
                'processargs': (),
                'processkwargs': {'Bucket': bucket, 'Key': key}
            },
        )
    action_multiprocessing(multiprocesses)

    # Third multiprocessing split.
    # Upload converted file to output bucket and delete local input.
    metadata = {"Metadata": {"id": conversionid, "sourcefileid": sourcefileid}}
    multiprocesses = (
        {
            'method': s3_client.upload_file,
            'processargs': (upload_path, os.environ['OutputBucket'], key,),
            'processkwargs': {'ExtraArgs': metadata}
        },
        {
            'method': os.remove,
            'processargs': (download_path,),
            'processkwargs': {}
        },
    )
    action_multiprocessing(multiprocesses)

    # Delete local output file.
    os.remove(upload_path)
