FROM php:7.2-apache

RUN apt-get update && apt-get install -qqy git unzip libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libaio1 wget && apt-get clean autoclean && apt-get autoremove --yes &&  rm -rf /var/lib/{apt,dpkg,cache,log}/ 
#composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ORACLE oci 
RUN mkdir /opt/oracle \
    && cd /opt/oracle     
    
ADD instantclient-basic-linux.x64-11.2.0.4.0.zip /opt/oracle
ADD instantclient-sdk-linux.x64-11.2.0.4.0.zip /opt/oracle

# Install Oracle Instantclient
RUN  unzip /opt/oracle/instantclient-basic-linux.x64-11.2.0.4.0.zip -d /opt/oracle \
    && unzip /opt/oracle/instantclient-sdk-linux.x64-11.2.0.4.0.zip -d /opt/oracle \
    && ln -s /opt/oracle/instantclient_11_2/libclntsh.so.11.1 /opt/oracle/instantclient_11_2/libclntsh.so \
#    && ln -s /opt/oracle/instantclient_11_2/libclntshcore.so.11.2 /opt/oracle/instantclient_11_2/libclntshcore.so \
    && ln -s /opt/oracle/instantclient_11_2/libocci.so.11.1 /opt/oracle/instantclient_11_2/libocci.so \
    && rm -rf /opt/oracle/*.zip
    
ENV LD_LIBRARY_PATH  /opt/oracle/instantclient_11_2:${LD_LIBRARY_PATH}
    
# Install Oracle extensions
RUN echo 'instantclient,/opt/oracle/instantclient_11_2/' | pecl install oci8 \ 
      && docker-php-ext-enable \
               oci8 \ 
       && docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/opt/oracle/instantclient_11_2,11.2 \
       && docker-php-ext-install \
               pdo_oci 
