    <h1>CSV Download Portal</h1>
    <p>Welcome to the CSV Download Portal, your one-stop destination for accessing a wide range of CSV databases tailored to your specific needs. This portal provides a streamlined and user-friendly interface for generating and downloading various CSV files. Whether you need comprehensive databases for Anytone devices or specific regional data, our portal ensures you have the right tools at your fingertips.</p>

    <h2>Features:</h2>
    <ul class="feature-list">
        <li><strong>Secure Access</strong>: Our portal ensures secure access with a login feature to protect sensitive data. Use the password "roodwitblauw" to access the resources.</li>
        <li><strong>Intuitive Navigation</strong>: Easily navigate between different sections of the portal using the menu options. Select the required section to generate and download the desired CSV file.</li>
        <li><strong>Home</strong>: Start here to get an overview of the portal's functionalities and select the desired options.</li>
        <li><strong>Download Full CSV</strong>: Generate and download the complete database in CSV format. Ideal for users needing a comprehensive data set.</li>
        <li><strong>Download Anytone CSV</strong>: Generate and download the full database specifically formatted for Anytone devices. Ensures compatibility and ease of use.</li>
        <li><strong>Download Dutch Database</strong>: Access and download a specialized database focusing on Dutch data for Anytone devices. Perfect for users in or working with data from the Netherlands.</li>
        <li><strong>Download Filtered Database</strong>: Customize your download by selecting specific countries. Generate and download a filtered database tailored to your needs.</li>
    </ul>

    <h2>Instructions for Use:</h2>
    <ul class="instructions-list">
        <li><strong>Login</strong>: Enter the password "roodwitblauw" in the login section to gain access to the portal.</li>
        <li><strong>Navigation</strong>: Use the navigation menu to select the desired section:
            <ul>
                <li><strong>Home</strong>: Overview and welcome message.</li>
                <li><strong>Download Full CSV</strong>: Download the complete database.</li>
                <li><strong>Download Anytone CSV</strong>: Download the full database formatted for Anytone devices.</li>
                <li><strong>Download Dutch Database</strong>: Download the Dutch-specific database for Anytone devices.</li>
                <li><strong>Download Filtered Database</strong>: Select a country and download a filtered database.</li>
            </ul>
        </li>
        <li><strong>Generate and Download CSV</strong>: In each section, click the download button to generate and download the selected CSV file.</li>
    </ul>

    <p>Our portal is designed to provide a seamless experience, ensuring you have quick and easy access to the data you need. For any questions or support, feel free to contact our helpdesk. Thank you for using the CSV Download Portal!</p>





# dmr-database-frontend

Soon more Readme


This is the frontend for the dmr-database in php

#
docker website

##
sudo docker run --name nginx-php-webserver-database -p 80:8080 --restart always -v $(pwd):/var/www/html trafex/php-nginx


#
docker compose for mariadb with adminer

##
version: '3.1'

services:

  db:
    image: mariadb:10.10
    restart: always
    environment:
      MARIADB_ROOT_PASSWORD: passw0rd
    ports:
      - 3306:3306

  adminer:
    image: adminer:latest
    restart: always
    ports:
      - 8085:8080
