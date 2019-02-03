[![Build Status](https://travis-ci.org/mattporritt/moodle-fileconverter_librelambda.svg?branch=master)](https://travis-ci.org/mattporritt/moodle-fileconverter_librelambda)

# Libre Lambda Document Converter #

This is a file converter plugin for the Moodle (https://moodle.org) Learning Management System (LMS). The primary function of this plugin is to convert student submissions into the PDF file format, to allow teachers to use the annotate PDF functionality of Moodle.

More information on the annotate PDF function of Moodle can be found: `https://docs.moodle.org/36/en/Using_Assignment#Annotating_submissions`

This plugin uses Amazon Web Services (AWS) tools to provide the conversion to PDF, the plugin interfaces Moodle with the AWS tools. Everything you need to setup both Moodle and AWS is included in this plugin.

The aims of this plugin are to:
* Provide PDF conversion functionality that is efficent and reliable at scale.
* Convert files to PDF in a cost effective manner.
* Provide confidence in data privacy and data security throughout the conversion process.
* Be straightforward to setup and use.

The following sections outline the steps that need to be followed to install the plugin, setup Moodle and the AWS architecture to enable document conversion. The installation and setup process has the following steps:

1. [Plugin Installation](#plugin-installation)
2. [AWS Stack setup](#aws-stack-setup)
3. [Plugin Setup](#plugin-setup)
4. [Moodle Setup](#moodle-setup)

## Supported Moodle Versions
This plugin currently supports Moodle:

* 3.5
* 3.6

## Plugin Installation
The following steps will help you install this plugin into your Moodle instance.

1. Clone or copy the code for this repository into your Moodle instance at the following location: `<moodledir>/files/converter/librelambda`
2. This plugin also depends on *local_aws* get the code from `https://github.com/catalyst/moodle-local_aws` and clone or copy it into `<moodledir>/local/aws`
3. Run the upgrade: `sudo -u www-data php admin/cli/upgrade` **Note:** the user may be different to www-data on your system.

Once the plugin is installed, next the AWS Stack needs setup.

**Note:** It is recommended that installation be completed via the command line instead of the Moodle user interface.

## AWS Stack Setup
The following steps will setup the Amazon Web Services (AWS) infrastructure. The AWS infrastructure is required to do the actual conversion of documents into PDF. While setting up the AWS infrastructure is largely automated by scripts included in this plugin, a working knowledge of AWS is highly recommended.

For more information on how the submitted files are processed in AWS please refer to the topic: [Conversion Architecture](#conversion-architecture)

This step should be completed once the plugin has been installed into your Moodle instance.

**Note:** Full support on setting up an AWS account and API access keys for AWS stack infrastructure provisioning is beyond the scope of this guide.

To setup the AWS conversion stack infrastructure:

1. Create an AWS account, see: `https://aws.amazon.com/premiumsupport/knowledge-center/create-and-activate-aws-account/` for information on how to do this.
2. Create an AWS API user with administrator access and generate a API Key ID and a API Secret Key, see: `https://docs.aws.amazon.com/IAM/latest/UserGuide/id_users_create.html` for information on how to do this.
3. Change to your Moodle instance application directory. e.g. `cd /var/www/moodle`
4. Run the provisioning script below, replacing `<keyid>` and `<secretkey>` With the AWS API Key ID and AWS API Secret Key that you obtained in step 2. <br/> Replace `<region>` with the AWS region you wish to set up your AWS stack, e.g. `ap-southeast-2`. The list of regions avialable can be found here: https://docs.aws.amazon.com/general/latest/gr/rande.html#lambda_region  <br/> The command to execute is:

    ```console
    sudo -u www-data php files/converter/librelambda/cli/provision.php \
    --keyid=<keyid> \
    --secret=<secretkey> \
    --region=<region> \
    --set-config
    ```
**Note:** the user may be different to www-data on your system.

The `--set-config` option will automatically set the plugin settings in Moodle based on the results returned by the provisioning script.

The script will return output similar to, the following:

    ```console
    == Creating resource S3 Bucket ==
    Created input bucket, at location http://ee27c5ac168fafae77a15bb7e60d6af0-resource.s3.amazonaws.com/

    == Uploading Libre Office archive to resource S3 bucket ==
    Libreoffice archive uploaded sucessfully to: https://ee27c5ac168fafae77a15bb7e60d6af0-resource.s3.ap-southeast-2.amazonaws.com/lo.tar.xz

    == Uploading Lambda archive to resource S3 bucket ==
    Lambda function archive uploaded sucessfully to: https://ee27c5ac168fafae77a15bb7e60d6af0-resource.s3.ap-southeast-2.amazonaws.com/lambdaconvert.zip

    == Provisioning the Lambda function and stack resources ==
    Stack status: CREATE_IN_PROGRESS
    Stack status: CREATE_IN_PROGRESS
    Stack status: CREATE_IN_PROGRESS
    Stack status: CREATE_COMPLETE
    Cloudformation stack created. Stack ID is: arn:aws:cloudformation:ap-southeast-2:693620471840:stack/LambdaConvertStack/4d609630-2760-11e9-b6a5-02181cf5d610

    == Provisioning the Lambda function and stack resources ==
    S3 user access key: AKIAI6TYTAIFC6GVUJYQ
    S3 user secret key: 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
    Input Bucket: ee27c5ac168fafae77a15bb7e60d6af0-input
    Output Bucket: ee27c5ac168fafae77a15bb7e60d6af0-output
    == Setting plugin configuration in Moodle, from returned settings. ==

    ```
## Plugin Setup

5. Set up the plugin in *Site administration > Plugins > Search > Manage global search* by selecting *elastic* as the search engine.
6. Configure the Elasticsearch plugin at: *Site administration > Plugins > Search > Elastic*
7. Set *hostname* and *port* of your Elasticsearch server
8. Optionally, change the *Request size* variable. Generally this can be left as is. Some Elasticsearch providers such as AWS have a limit on how big the HTTP payload can be. Therefore we limit it to a size in bytes.
9. To create the index and populate Elasticsearch with your site's data, run this CLI script. `sudo -u www-data php search/cli/indexer.php --force`
10. Enable Global search in *Site administration > Advanced features*

## Moodle setup
Enable annotation
ghostscript
enable document converters

https://docs.moodle.org/36/en/Using_Assignment#Annotating_submissions

## Additional Information
The following sections provide an overview of some additional topics for this plugin and it's associated AWS architecture.

### Conversion Architecture
TODO: this

### Privacy and Data Control
TODO: this

### Cost Profiling
TODO: this

### Compiling Libre Office
TODO: this

### Lambda Function
TODO: this

### Why AWS?
TODO: this

### Why make this plugin?
Moodle currently ships with two (2) file converter plugins

Ease of use and scalability 


## License ##

2018 Matt Porritt <mattp@catalyst-au.net>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.
