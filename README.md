# zootaxa-metadata-harvest
Harvest Zootaxa bibliographic details from web site and PDFs.

## Background
This project aims to harvest bibliographic metadata for the journal Zootaxa from the web site and the PDF previews, and export them in various formats. The first format is OJS, with the ultimate goal of helping Mapress get all the back issues of Zootaxa into their OJS site http://biotaxa.org.

The code parses HTML scrapped from the http://mapress.com web site, and supplements this with details extracted from PDFs using pdftoxml. It outputs OJS XML, as well as HTML previews to help check for errors.

## Open Access
The test for open access is simply whether accessing the URL for the complete PDF returns a HTTP 200 OK (open access) or HTTP/1.1 401 Authorization Required (behind paywall).

## ZIP file

To create an archive of all OJS XML files copy them to ohs-all-xml then:

   zip -r ojs.zip *.xml 2006 2007 2008 2009 2010 2011 2012


