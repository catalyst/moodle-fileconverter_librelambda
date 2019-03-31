This folder contains files related to running the document conversion process in AWS.
These files are not called from Moodle Directly.

lambdaconvert.py
This file is Python 3 code that the Lambda function executes.
Updates to this file need to be also reflected in lambdaconvert.zip

lambdaconvert.zip
This is the compressed version of lambdaconvert.py
Lambda code uploaded to AWS and invoked via Cloudformation needs to be
in a compressed Zip archive. If lambdaconvert.py is changed then this file
needs to be updated with the latest version.

stack.tempate
This is the AWS Cloudformation (https://aws.amazon.com/cloudformation/) template
used to set up the AWS stack and associated service used for document conversion.
It is called by the provisioning command line script.
