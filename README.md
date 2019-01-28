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

For more information on how the submitted files are process in AWS please refer to the topic: [Conversion Architecture](#conversion-architecture)

This step should be completed once the plugin has been installed into your Moodle instance.

**Note:** Full support on setting up an AWS account and API access keys for AWS stack infrastructure provisioning is beyond the scope of this guide/

To setup the AWS conversion stack infrastructure:

1. Create an AWS account, see: `https://aws.amazon.com/premiumsupport/knowledge-center/create-and-activate-aws-account/` for information on how to do this.
2. Create an AWS API user with administrator access, see: `https://docs.aws.amazon.com/IAM/latest/UserGuide/id_users_create.html` for information on how to do this.
3. 

4. Set up the plugin in *Site administration > Plugins > Search > Manage global search* by selecting *elastic* as the search engine.
5. Configure the Elasticsearch plugin at: *Site administration > Plugins > Search > Elastic*
6. Set *hostname* and *port* of your Elasticsearch server
7. Optionally, change the *Request size* variable. Generally this can be left as is. Some Elasticsearch providers such as AWS have a limit on how big the HTTP payload can be. Therefore we limit it to a size in bytes.
8. To create the index and populate Elasticsearch with your site's data, run this CLI script. `sudo -u www-data php search/cli/indexer.php --force`
9. Enable Global search in *Site administration > Advanced features*

## Plugin Setup

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
