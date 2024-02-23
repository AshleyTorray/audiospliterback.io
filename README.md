1. check the NodeJs and Composer, mysql,  if none, install them.
    - check the NodeJs vedrsion
        ```shell
            $ node -v
        ````
    * if node version don't shown, run install NodeJs
        ```shell
            $ sudo apt install nodejs npm -y
        ````
    - for composer, install php-cli!
        ```shell
            $ sudo apt install php-cli unzip
        ````
    *  You will be prompted to confirm installation by typing Y and then ENTER.
    - when finish the cli, install the composer  
        ```shell
            $ sudo php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
        ````
    - install Mysql server
        ```shell
            $ sudo apt install mysql-server-y
            $ sudo mysql_secure_installation
        ````
2. Configure the laravel backend project

    - go to new Terminal and run the command.
        ```shell
            $ npm install
            $ npm run prod
        ```` 
    - create .env from .env.example and generate the app key
        ```shell
            $ cp .env.example .env
            $ php artisan key:generate
        ```` 
    - migrate the database
        ```shell
            $ php artisan migrate
        ```` 
    - adjust the folder permission
        ```shell
            # Set the correct folder permissions for Laravel
            $ sudo chown -R www-data:www-data /var/www/your_project
            $ sudo find /var/www/your_project -type f -exec chmod 664 {} \;
            $ sudo find /var/www/your_project -type d -exec chmod 775 {} \;
            # If you have additional folders that need to be writable, adjust permissions accordingly

            $ sudo chmod -R ug+rwx storage bootstrap/cache
        ````
    - create the folders in follow url.
        go to '[project root]/storage/app/public' using Terminal, you have to create 3 folders.
        ```shell
            $ sudo mkdir audio
            $ sudo mkdir convert
            $ sudo mkdir log
        ````
        here, audio directory is need for uploading the recorded audio *.wav files.
        And convert directory is need for downloading the splited audio *.mp3 files.
        log directory is need for uploading the excel log files from the iiko.com
3. Run the project
    - Run the command in project root directory as follow. this works for checking new uploaded audio files in audio diretor and split to convert directory
        ```shell
            $ php artisan search:new-audio-files
        ````
    - Second, open new terminal and run command. this works for checking new uploaded excel files in log directory and saving the log info in databse
        ```shell
            $ php artisan search:excel-log
        ```` 
4. Of course, if you have not a mind to follow above steps,  we'll create Bash script and when the ubuntu sever run, the project can be runned at starting.