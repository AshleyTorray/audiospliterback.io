Next, publish the package configuration file by running the following command:
```shell
    php artisan vendor:publish --provider="Maatwebsite\Excel\ExcelServiceProvider"
````
This command will create a config/excel.php file where you can customize your Excel import settings.