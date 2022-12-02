![GitHub Workflow Status (branch)](https://img.shields.io/github/actions/workflow/status/catalyst/moodle-fileconverter_librelambda/ci.yml?branch=master)

# Libre Lambda Document Converter #

This is a file converter plugin for the Moodle (https://moodle.org) Learning Management System (LMS). The primary function of this plugin is to convert student submissions into the PDF file format, to allow teachers to use the annotate PDF functionality of Moodle.

More information on the annotate PDF function of Moodle can be found:

https://docs.moodle.org/36/en/Using_Assignment#Annotating_submissions

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

* 3.4
* 3.5
* 3.6
* 3.7
* 3.8
* 3.9
* 3.10
* 3.11

## Plugin Installation
The following steps will help you install this plugin into your Moodle instance.

1. Clone or copy the code for this repository into your Moodle instance at the following location: `<moodledir>/files/converter/librelambda`
2. This plugin also depends on *local_aws* get the code from `https://github.com/catalyst/moodle-local_aws` and clone or copy it into `<moodledir>/local/aws`
3. Run the upgrade: `sudo -u www-data php admin/cli/upgrade`

**Note:** the user may be different to www-data on your system.

Once the plugin is installed, next the Moodle setup needs to be performed.

**Note:** It is recommended that installation be completed via the command line instead of the Moodle user interface.

## Moodle setup
The following steps are required to setup PDF annotation in Moodle.

### Enable Annotation
PDF Annotation needs to be enabled at site level, for your Moodle installation. To do this:


1. Log into the Moodle UI as a site administrator
2. Navigate to the server system path settings: *Site administration > Plugins > Activity modules > Assignment > Feedback plugins > Annotate PDF*
3. Make sure the *Enabled by default* check box is checked
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
== Provisioning the Lambda function and stack resources ==
Stack status: CREATE_IN_PROGRESS
Stack status: CREATE_IN_PROGRESS
Stack status: CREATE_IN_PROGRESS
Stack status: CREATE_COMPLETE
Cloudformation stack created. Stack ID is: arn:aws:cloudformation:ap-southeast-2:693620471840:stack/LambdaConvert/4d609630-2760-11e9-b6a5-02181cf5d610

== Converter params ==
S3 user access key: AKIAxxxxxxxxxxxxxxxx
S3 user secret key: xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Input Bucket: xxxxxxxxxxxxxxxxxxxxxxxx-input
Output Bucket: xxxxxxxxxxxxxxxxxxxxxxxx-output
== Setting plugin configuration in Moodle, from returned settings. ==
```

What is created in AWS land:

  * A resource bucket with libreoffice conversion binary and python script that lambda executes
  * A stack with:
    + input and output bucket
    + a lambda function that is executed on upload to input bucket
    + a user and a set of roles/policies to govern execution and access permissions
    + a par of secret/access keys

### Multiple stacks

The provisioning script creates a stack with the default name of LambdaConvert. If you need to give it a different name, or want multple stacks, there's `--stack-name` option, eg:

```console
sudo -u www-data php files/converter/librelambda/cli/provision.php \
--keyid=<keyid> \
--secret=<secretkey> \
--region=<region> \
--stack-name=LambdaConvertTest
--set-config
```

### Updating (reprovisioning)

Running the provision script again will replace (reprovision) the stack.

In order to avoid accidental overwriting, `--replace-stack` option must be given when updating:

```console
sudo -u www-data php files/converter/librelambda/cli/provision.php \
--keyid=<keyid> \
--secret=<secretkey> \
--region=<region> \
--set-config

Stack status: CREATE_COMPLETE
Stack exsists and replacement not requested.
If you want to replace the stack use "--replace-stack" option

...

sudo -u www-data php files/converter/librelambda/cli/provision.php \
--keyid=<keyid> \
--secret=<secretkey> \
--region=<region> \
--replace-stack \
--set-config

== Provisioning the Lambda function and stack resources ==
...
```

### Stack removal

Stack can be remoced with `--remove-stack` option.

```console
sudo -u www-data php files/converter/librelambda/cli/provision.php \
--keyid=<keyid> \
--secret=<secretkey> \
--region=<region> \
--remove-stack

Stack status: CREATE_COMPLETE
Do you really want to remove "LambdaConvert" stack? [Type "yes" to confirm]:

yes[ENTER]

Stack status: DELETE_IN_PROGRESS
Removed
```

### Common errors
#### Removing non-empty bucket
Buckets that are not empty cannot be removed. This error may occur when removing stack,
or updating stack - sometimes stack won't update, in which case we do remove/create.

This error will be visibly reported.

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
--use-sdk-creds=0
```

To use credential set in AWS Credentials File, use use-sdk-creds=1.
(https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials_profiles.html)
```console
sudo -u www-data php files/converter/librelambda/cli/test.php \
--region=<region> \
--input-bucket=<inputbucket> \
--output-bucket=<outputbucket> \
--file='/var/www/moodle/files/converter/librelambda/tests/fixtures/testsubmission.odt'
--use-sdk-creds=1
```

**Note:** the user may be different to www-data on your system.
**Note:** for unknown reasons running test first time after pushing to AWS stack may fail - just repeat.

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
The below image shows the high level architecture the plugin provisioning process sets up in AWS.

![Conversion Architecture](/pix/LibreLambda.png?raw=true)

The conversion process and AWS architecture is relatively simple:

* Moodle uploads the document to be converted into the input bucket.
* An event is triggered when the document is successfully uploaded, the Lambda function is then invoked by this event and begins the conversion process.
* The original document is fetched from the bucket by the Lambda function.
* The Lambda function uses LibreOffice to convert the document. LibreOffice is never constantly running it is started by the Lambda function and stops automatically when the conversion in complete
* The converted document is uploaded by the Lmabda function to the output bucket
* The temporary copies of the document and the original in the input bucket are deleted
* Moodle retrieves the converted document from the output bucket.

There are no traditional *servers* or compute resources involved in the conversion process. Storage for the uploaded and converted documents is provide by S3 (an AWS object storage service). The conversion processing is handled by Lambda (an AWS Function on Demand runtime service). This means compute resources are only used when they are invoked by a document upload and they are stopped when the document conversion is finished.

This architecture is also very scalable and can handle a high degree of parallelism. Every time a document is uploaded to the input bucket a new Lambda function is invoked (upto an initial limit of 1,000).  The lambda functions are fully self contained and have everything they need to convert a document. This means newly uploaded documents don't need to wait for previous documents to be converted before their own conversion starts.

### Privacy and Data Control
Student data privacy is very important, especially when sending data out of Moodle and supplying it to third party services. This plugin was designed with privacy and security in mind.  Some of the privacy and security features are outlined below.

* No copies of documents are stored in AWS after document conversion.
  * All data sent to the AWS is transient it exists only for the length of the conversion. Files uploaded to the input bucket are deleted when they are processed by the Lambda function.  All temporary files created by the conversion are explicitly deleted as part of the conversion cleanup. The converted file in the output bucket is deleted by Moodle when the converted file is retrieved by Moodle.
* All document data sent and retrieved from AWS is encrypted in transit.
  * SSL/TLS is used between Moodle and the AWS infrastructure for both uploading and downloading documents
* Enforced access control
  * Access control is enforced in several places. The credentials that the plugin uses to contact AWS only have access to the input and output buckets for the provisioned conversion architecture. The credentials give them no other access to any other AWS services or infrastructure.
  * The access granted to the Lambda function is limited to downloading and uploading documents between the input and output buckets. The Lambda function has no other access to any other AWS services or infrastructure.
* We control the entire workflow (and conversion process is known).
  * Even though we are sending data to a third party (AWS). What happens to the data is completely controlled and known by us. The workflow is known as is the steps taken by the Lambda function. We have access to all the code that is used for the entire process and we can track the data at each step.
* Document names are obfuscated and no other student data is transmitted out of Moodle.
  * The document to be converted is uploaded to AWS with the original document name replace with a cryptographic hash. That way even individuals with access to the buckets and conversion logs, can find any student details from the document filename. NOTE: users with access to the input and output documents will be able to read the documents.
  * No student data is included as part of the conversion process.  Some additional metadata is passed along with the document to convert, but this is not related to a Moodle user.

### Cost Profiling
The following outlines the costs involved using this plugin to convert 100,000 documents to PDF.
All costs are in AUD.

Costs for cloud based services have a lot of individual elements and can be confusing. Therefore it is often
better to use a concrete example. Below is the cost breakdown for the conversion test undertaken of 100,000
source documents. The 100,000 documents require 38GB of storage space.

|                            |             |            |            |                              |
|----------------------------|-------------|------------|------------|------------------------------|
| Documents to convert       | 100,000     |            |            |                              |
| Avg File Size (MB)         | 0.38        |            |            |                              |
|                            |             |            |            |                              |
| Operation                  | Unit        | Unit cost  | Total      | Notes                        |
| Put to S3 Input bucket     | Per Request | 0.0000055  | $0.55      |                              |
| S3 Input Bucket Storage    | Per GB      | 0.025      | $0.95      |                              |
|                            |             |            |            |                              |
| S3 Input to Lambda         | Per Request | 0.00000044 | $0.04      |                              |
| Lambda Invocations         | Per Request | 0          | $0.00      | First million per month free |
| Lambda Executions          | GB/s        | 0          | $0.00      | 400,00 GB-Seconds month free |
| Lambda to S3 Output        | Per Request | 0.0000055  | $0.55      |                              |
|                            |             |            |            |                              |
| S3 Output Bucket Storage   | Per GB      | 0.025      | $0.95      |                              |
| Get from S3 Output Bucket  | Per Request | 0.00000044 | $0.04      |                              |
| First GB transfer out      | Per GB      | 0          | $0.00      |                              |
| 1GB - 9.999TB Transfer out | Per GB      | 0.114      | $4.22      |                              |
|                            |             |            |            |                              |
|                            |             | Total      | $7.31      |                              |
|                            |             | Per doc    | $0.0000731 |                              |

Cost profiling resources:

* [Amazon S3 Pricing](https://aws.amazon.com/s3/pricing/?nc=sn&loc=4)
* [AWS Lambda Pricing](https://aws.amazon.com/lambda/pricing/)

### Libre Office Archive and Compliation
This plugin includes precompiled LibreOffice archives as a compressed archive in the */libre* folder of this repository. The archive is uploaded to AWS as part of the provisioning process. Lambda uses the uncompressed binaries to do the actual conversion of the uploaded documents to PDF.

The precompiled binary archive for LibreOffice is provided as a convienence to make setting everything up easier. However, you can obtain the LibreOffice source code and compile it yourself. See the section: *Compiling Libre Office* for instructions on how to do this.

#### Compiling Libre Office
This section will outline how to compile LibreOffice for yourself to be used by AWS Lambda to convert files.

There are two main reasons why we need to custom compile LibreOffice to work with AWS Lambda. The first is we need to compile LibreOffice so it works in the same runtime environment as Lambda. The second is that Lambda has very limited disk space (512MB) which we can use to store and execute binaries. So we need to create a very minimal version of LibreOffice to stay under the disk space limits.

Knowledge of Docker as well as command line Linux administration is required to compile your own LibreOffice installation.

The process to create your own compiled LibreOffice binary archive is:
* Get the LibreOffice code from the LibreOffice project
* Compile LibreOffice binaries in a container based on one used for AWS Lambda
* Create the LibreOffice archive

Following steps are done in the `librelambda/build` directory.

##### Get the LibreOffice code from the LibreOffice project

    wget https://download.documentfoundation.org/libreoffice/src/6.4.7/libreoffice-6.4.7.2.tar.xz

This is the latest version in the 6 family at the time of writing. Amazon 2 image is based on Centos 7,
which proved challenging enough to make us not even consider trying with LibreOffice 7.

In (unlikely) case that another minor version is requested, `docker build` observes `lo_ver` env var, eg
    lo_ver=6.4.7.5 docker build ...

##### Compile LibreOffice binaries
Build the image and launch a container. Depending on your circumnstances/preferences you may use
slightly different arguments with `docker` commands:

    docker build -t lo-build .
    # OR for arm64/aarch64 (graviton):
    docker build -t lo-build --build-arg ARCH=aarch64 .
    docker run -it --rm lo-build bash

This should give you a shell in a running container. In the container shell:

    make
    # OR for arm64/aarch64 (graviton):
    CPPFLAGS="-DPNG_ARM_NEON_OPT=0" make
    strip instdir/program/*

Once the binaries are compiled (and stripped) you can run the following commands (still in the container shell)
to test the conversion. You will probably get a fontconfig warning, ignore:

    echo "hello world" > /tmp/a.txt
    ./instdir/program/soffice.bin --headless --invisible --nodefault --nofirststartwizard \
        --nolockcheck --nologo --norestore --convert-to pdf --outdir /tmp /tmp/a.txt
    ls -l /tmp/a.pdf

**Note:** For some reason conversion may silently do nothing. In that case just re-run it.

If everything seems fine, pack it up:

    tar -cf /lo.tar instdir

Now you have lo.tar in the running container. Do not exit it yet. In another terminal on your computer:

    docker ps
    docker cp <container id>:/lo.tar .
`docker ps` should give you the id of the `lo-build` running container that you will use for copying.

After checking that you have `lo.tar` you can leave the container.

**NOTE:** Compiling LibreOffice will take time.

**If something goes wrong:**

    docker run -it --rm --cap-add=SYS_PTRACE --security-opt seccomp=unconfined lo-build bash

If you are rebuilding the `lo-build` image, you can copy tarballs from the running container's
`<build dir>/external/tarballs/` to the `tarballs` directory. That will save you downloading each time you start new container.

After the above steps are completed follow the instructions in the next section, to create the LibreOffice archive.

##### Create the LibreOffice archive
Now is time to remove unneeded things from lo.tar if you wish
(share/gallery,template,fonts/truetype/EmojiOneColor-SVGinOT.ttf...). Either untar/remove/tar back, or

    tar -f lo.tar --delete xyz...

Once you are happy with the content of `lo.tar`:

    xz -e9 lo.tar

And replace the existing `lo.tar.xz`:

    mv lo.tar.xz ..

Next time you run the provisioning script to setup the environment it will use the newly created LibreLambda archive for Lambda.

### Lambda Function
TODO: this

## FAQs

### Why make this plugin?
Moodle currently ships with two (2) file converter plugins: Unoconv and Google Drive Converter. These plugins use external services to convert submitted files to PDF. In our experience using these plugins for production Moodle instances, both have issues. These issues are especially bad in sites that convert a lot of files.  The issues are mainly related to performance but there are privacy concerns as well.  In order to address these issues we decided to make this plugin.

The following is the broad criteria we used when making this plugin and this plugin aims to address all of these issues:

* **Privacy** - We want to know where our data is, how it is used and who it is shared with at all points of the conversion journey. Unoconv is good here as you’re in complete control of the process and libreoffice itself is an open project. Google drive converter on the other hand is a black box.

* **Cost** -  The cost per document conversion, including costs for solution maintenance need to be as low as possible. Google converter and Unoconv are not too bad here. Unoconv loses out a bit when you include the costs for managing the infrastructure.

* **Scalability and Performance** - Whatever we chose we needed it to work at scale, that is reliably convert a large number of documents quickly. Both Unoconv and the Google converter fall down here. Google can get slow during peak periods. And Unoconv infrastructure is hard to manage at scale.

* **Ease of Management and Set Up** - Finally the solution needs to be easy to set up, for both production and development environments and ongoing management should be minimal.

### Does my Moodle need to be in AWS?
No, you’re Moodle instance doesn’t need to reside in AWS to use this plugin. As long as your Moodle can contact the AWS S3 endpoint via HTTPS you should be able to use this plugin. This includes development environments.

### How long does document conversion take?
Typical conversion times are between 4 - 80 seconds. This is how long the conversion takes in AWS, the time it takes for the converted document to be available in Moodle depends on the timing of cron runs.

Conversion time is variable and depends on the source document. Also if you haven't done a conversion for a while there may be a "warm up" time for the AWS architecture.

### Why AWS?
TODO: this

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
