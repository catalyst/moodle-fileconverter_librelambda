[![Build Status](https://travis-ci.org/mattporritt/moodle-fileconverter_librelambda.svg?branch=master)](https://travis-ci.org/mattporritt/moodle-fileconverter_librelambda)

# Libre Lambda Document Converter #

This is a file converter plugin for the Moodle (https://moodle.org) Learning Management System (LMS). The primary function of this plugin is to convert student submissions into the PDF file format, to allow teachers to use the annotate PDF functionality of Moodle.

More information on the annotate PDF function of Moodle can be found: `https://docs.moodle.org/36/en/Using_Assignment#Annotating_submissions`

This plugin uses Amazon Web Services (AWS) services to provide the conversion to PDF, the primary AWS services used are [Lambda](https://aws.amazon.com/lambda/) and [S3](https://aws.amazon.com/s3/). The plugin interfaces Moodle with the AWS services. Everything you need to setup both Moodle and AWS is included in this plugin.

The aims of this plugin are to:
* Provide PDF conversion functionality that is efficient and reliable at scale.
* Convert files to PDF in a cost effective manner.
* Provide confidence in data privacy and data security throughout the conversion process.
* Be straightforward to setup and use.

The following sections outline the steps that need to be followed to install the plugin, setup Moodle and the AWS architecture to enable document conversion. The installation and setup process has the following steps:

1. [Plugin Installation](#plugin-installation)
2. [Moodle Setup](#moodle-setup)
3. [AWS Stack setup](#aws-stack-setup)
4. [Plugin Setup](#plugin-setup)

## Supported Moodle Versions
This plugin currently supports Moodle:

* 3.5
* 3.6

## Plugin Installation
The following steps will help you install this plugin into your Moodle instance.

1. Clone or copy the code for this repository into your Moodle instance at the following location: `<moodledir>/files/converter/librelambda`
2. This plugin also depends on *local_aws* get the code from `https://github.com/catalyst/moodle-local_aws` and clone or copy it into `<moodledir>/local/aws`
3. Run the upgrade: `sudo -u www-data php admin/cli/upgrade` **Note:** the user may be different to www-data on your system.

Once the plugin is installed, next the Moodle setup needs to be performed.

**Note:** It is recommended that installation be completed via the command line instead of the Moodle user interface.

## Moodle setup
The following steps are required to setup PDF annotation in Moodle.

### Enable Annotation
PDF Annotation needs to be enabled at site level, for your Moodle installation. To do this:


1. Log into the Moodle UI as a site administrator
2. Navigate to the server system path settings: *Site administration > Plugins > Activity modules > Assignment > Feedback plugins > Annotate PDF*
3. Make sure the *Enabled by default* checkbox is checked
4. Click *Save changes*

### Set Ghostscript Executable
Moodle uses Ghostscript (https://www.ghostscript.com/) to annotate the PDF files themselves. To use PDF Annotation your Moodle instance must be able to reach the Ghostscript executable. To do this:

1. Log into the Moodle UI as a site administrator
2. Navigate to the System path settings: *Site administration > Server > System paths*
3. Enter in the path to the Ghostscript executable in the *Path to ghostscript* setting text box.
4. Click *Save changes*

**Note:** In some Moodle installations setting system paths is disabled. You may need to contact your system administrator or Moodle vendor to have this value set.

### Enable Document Converter
The Libre Lambda document converter must be enabled in Moodle before it can be used to convert documents. To do this:

1. Log into the Moodle UI as a site administrator
2. Navigate to the Manage document converter settings: *Site administration > Plugins > Document converters > Manage document converters*
3. Click the enable *eye icon* in the table row that corresponds to: *Libre Lambda Document Converter*

Before the converter can be used the required AWS infrastructure needs to be setup. This is covered in the next section.

## AWS Stack Setup
The following steps will setup the Amazon Web Services (AWS) infrastructure. The AWS infrastructure is required to do the actual conversion of documents into PDF. While setting up the AWS infrastructure is largely automated by scripts included in this plugin, a working knowledge of AWS is highly recommended.

For more information on how the submitted files are processed in AWS please refer to the topic: [Conversion Architecture](#conversion-architecture)

This step should be completed once the plugin has been installed into your Moodle instance and the other Moodle setup tasks have been completed.

**Note:** Full support on setting up an AWS account and API access keys for AWS stack infrastructure provisioning is beyond the scope of this guide.

To setup the AWS conversion stack infrastructure:

1. Create an AWS account, see: `https://aws.amazon.com/premiumsupport/knowledge-center/create-and-activate-aws-account/` for information on how to do this.
2. Create an AWS API user with administrator access and generate a API Key ID and a API Secret Key, see: `https://docs.aws.amazon.com/IAM/latest/UserGuide/id_users_create.html` for information on how to do this.
3. Change to your Moodle instance application directory. e.g. `cd /var/www/moodle`
4. Run the provisioning script below, replacing `<keyid>` and `<secretkey>` With the AWS API Key ID and AWS API Secret Key that you obtained in step 2. <br/> Replace `<region>` with the AWS region you wish to set up your AWS stack, e.g. `ap-southeast-2`. The list of regions available can be found here: https://docs.aws.amazon.com/general/latest/gr/rande.html#lambda_region  <br/> The command to execute is:

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
Libreoffice archive uploaded successfully to: https://ee27c5ac168fafae77a15bb7e60d6af0-resource.s3.ap-southeast-2.amazonaws.com/lo.tar.xz

== Uploading Lambda archive to resource S3 bucket ==
Lambda function archive uploaded successfully to: https://ee27c5ac168fafae77a15bb7e60d6af0-resource.s3.ap-southeast-2.amazonaws.com/lambdaconvert.zip

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

Once the AWS stack infrastructure setup has been completed, next the Libre Lambda converter plugin in Moodle needs to be configured.

**Note:** These steps only needs to be completed if you did not use the `--set-config` option when running the AWS stack setup provisioning script.
otherwise the plugin will be setup, you can use the steps below to verify.

To configure the plugin in Moodle:

1. Log into the Moodle UI as a site administrator
2. Navigate to the Libre Lambda Document converter settings: *Site administration > Plugins > Document converters > Libre Lambda Document Converter*
3. Enter the values for: `Key`, `Secret`, `Input bucket`, `Output bucket`, and `Region` from the corresponding values returned by the provisioning script. E.g. Region: ap-southeast-2
4. Click *Save changes*

## Testing Document Conversion
There are two ways to test the document conversion. The first is by a command line test script that tests the AWS architecture independent of Moodle. The second uses the regular Moodle workflow to test the conversion process end to end. The following sections outline both.

### Conversion test script
Once the AWS architecture has been setup using the provisioning script, it can be tested from the command line.

The following test command runs a basic conversion in AWS and returns the result status. To run the script:

1. Change to your Moodle instance application directory. e.g. `cd /var/www/moodle`
2. Run the following command, replacing `<keyid>` and `<secretkey>` With the AWS API Key ID and AWS API Secret Key that you obtained in the AWS Stack Setup. <br/> Replace `<region>` with the AWS region from the  AWS stack set, e.g. `ap-southeast-2`. <br/> Replace `<inputbucket>` and `<outputbucket>` with the buckets from the setup. <br/> Finally enter the path to the file wish to convert to PDF.:

```console
sudo -u www-data php files/converter/librelambda/cli/test.php \
--keyid=<keyid> \
--secret=<secretkey> \
--region=<region> \
--input-bucket=<inputbucket> \
--output-bucket=<outputbucket> \
--file='/var/www/moodle/files/converter/librelambda/tests/fixtures/testsubmission.odt'
```

**Note:** the user may be different to www-data on your system.

### Moodle assignment conversion 

A full end to end test can be performed in Moodle. This section outlines this process.

**Note:**  Cron must be configured in your Moodle instance for document conversion to operate. Information on setting up Cron on your Moodle instance can be found here: https://docs.moodle.org/36/en/Cron

To setup in Moodle:

1. Log into the Moodle UI as a site administrator.
2. Create a new Moodle course.
3. Create a new Moodle user.
4. Enrol the user as a student in the course created in step 2.
5. In the Moodle course, create an assignment activity.
6. In the assignment setup, enable *File submissions* is enabled as a submission type.
7. In the assignment setup, enable *Annotate PDF* as a feedback type.
8. Log into Moodle as the test student user.
9. Submit an assignment as the test student.
10. Wait for the system cron to run.
11. Log back into Moodle as an administrator.
12. Access the course and then the assignment.
13. Click on grade in the assignment screen.
14. The PDF of the submission should be displayed.

## Additional Information
The following sections provide an overview of some additional topics for this plugin and it's associated AWS architecture.

### Conversion Architecture
TODO: this

### Privacy and Data Control
TODO: this

### Cost Profiling
TODO: this
cost elements

### Compiling Libre Office
TODO: this

### Lambda Function
TODO: this

## FAQs

### Why AWS?
TODO: this

### Why make this plugin?
Moodle currently ships with two (2) file converter plugins

### Does my Moodle need to be in AWS?
No, you’re Moodle instance doesn’t need to reside in AWS to use this plugin. As long as your Moodle can contact the AWS S3 endpoint via HTTPS you should be able to use this plugin. This includes development environments.

## Inspiration
This plugin was inspired by and based on the initial work done by [Vlad Holubiev](https://hackernoon.com/how-to-run-libreoffice-in-aws-lambda-for-dirty-cheap-pdfs-at-scale-b2c6b3d069b4) to compile and run Libre Office within an AWS Lambda function.

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
