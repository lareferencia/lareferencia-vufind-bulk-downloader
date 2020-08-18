## Vufind Search Results Bulk Downloader Service and Front-End Customization

Service, templates, controllers, and forms for customizing VuFind so the search results can be exported in CSV format. An "Export CSV" link is added to the search results page, opening a form where the user informs their email address and provides a captcha-based form validation. The actual CSV file creation is performed by the external service, which is called by VuFind and returns a file download link, which is in turn emailed to the user.

*Developed by LA Referencia / IBICT*

### Running the export service

TBD

### Implementing the customizations in VuFind

In the `vufind` folder:

1. Configuration file `bulkexport.ini`

- Copy the export configuration file to `<VUFIND_HOME>\config\vufind`
- Adjust the entries as needed, especially sections `Service` and `Captcha`
- ReCaptcha keys are obtained at http://www.google.com/recaptcha/admin upon domain registration

2. Theme folder `ibict`

- In case there is no custom theme:
	- Copy the whole folder to `<VUFIND_HOME>\themes` (the folder name, which is the new theme name, can be changed)
	- In the `config.ini` file (at `<VUFIND_HOME>\config\vufind`), change the `theme` entry to match the new theme name (the theme folder name)
	
- In case there is a custom theme already in use:
	- If there are no custom templates, copy the whole `templates` folder to the theme folder
	- If there are already custom templates:
		- Copy the whole `bulkexport` folder to the `templates` folder
		- Copy the whole `search` folder to the `templates` folder if it still does not exist, or copy only the `results.phtml` template file (under `search`)  to the existing local `search` folder otherwise
			- If the `results.phtml` template is already customized, only the lines 130--133, which include the "Export CSV" link, can be inserted in the existing customized template as desired, as long as the `href` attribute is not changed
		
3. Module folder `custom`

- VuFind must already have a custom module configured, which is created when it is installed

- If the `module.config.php` file in `<VUFIND_HOME>\module\<CUSTOM_MODULE>\config` has no configuration (the `$config` array variable is empty), it can be replaced by the `module.config.php` file in `custom\config`. If there are already custom configurations, the entries in the `custom\config\module.config.php` (which configure the new controller and static routes) must be inserted in the existing module config file. Check the VuFind module configuration file at `<VUFIND_HOME>\module\VuFind\config` to see where controller and static routes entries must be added. In any case, be sure to replace `custom` in the file paths with your own custom module name

- Copy the `Controller` folder in `custom\src\custom` to `<VUFIND_HOME>\module\<CUSTOM_MODULE>\src\<CUSTOM_MODULE>` if it still does not exist, or copy only the `BulkExportController.php` class (under `Controller`) to the existing local `Controller` folder otherwise
	- Edit the `BulkExportController.php` class to replace `custom` with your own custom module name in the namespace and the path to the `BulkExportConfirm` class (lines 3 and 5)
	
- Copy the `BulkExportConfirm.php` class to `<VUFIND_HOME>\module\<CUSTOM_MODULE>\src\<CUSTOM_MODULE>`, and edit the file to replace `custom` with your own custom module name in the namespace (line 3)

- Copy the `ExecuteBulkExport.php` class to `<VUFIND_HOME>\module\<CUSTOM_MODULE>\src\<CUSTOM_MODULE>`. This is the class which will be called in background to perform the export, so be sure to inform its full path in the `bulkexport.ini` file (see above), in the `backgroundClass` entry

VuFind should now include the new template and custom module files. If the new routes are not being found, delete the config cache at `<VUFIND_HOME>\local\cache\configs` so the new configuration, including the new routes, are correctly loaded.
