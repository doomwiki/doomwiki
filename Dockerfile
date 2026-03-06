FROM amazonlinux:2 AS base

EXPOSE 8080

# Install packages
RUN yum -y update && yum -y install \
    shadow-utils \
    sudo \
    crontabs \
    tar \
    wget \
    git \
    amazon-efs-utils \
    nano \
    xz \
    patch \
    procps-ng \
    aws-kinesis-agent && \
    yum clean all

# Enable PHP 7.4 from amazon-linux-extras and install apache httpd + php-fpm
RUN yum -y install amazon-linux-extras \
    && amazon-linux-extras enable php7.2 \
    && yum clean metadata \
    && yum -y install httpd \
    && yum -y install php-pecl-apcu php-pecl-memcached php-{bcmath,cli,curl,devel,fpm,gd,json,ldap,mbstring,mysqlnd,opcache,pdo,pdo_mysql,pear,sodium,xml} \
    && yum -y install ImageMagick librsvg2-tools \
    && yum clean all \
    && mkdir -p /run/php-fpm \
    && chown apache:apache /run/php-fpm

RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=64'; \
    echo 'opcache.interned_strings_buffer=4'; \
    echo 'opcache.max_accelerated_files=2000'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.save_comments=1'; \
} > /etc/php.d/99-opcache.ini

# We need python for reasons
RUN yum -y install python3 && yum clean all

# Add customizations and required elements for apache httpd and php
COPY --chown=root:root infrastructure/doomwiki.vhost.conf /etc/httpd/conf.d
COPY --chown=root:root infrastructure/compression.conf /etc/httpd/conf.d
COPY --chown=root:root infrastructure/access.conf /etc/httpd/access.conf
COPY --chown=root:root infrastructure/php.custom.ini /etc/php.d/40-doomwiki-custom.ini
COPY --chown=root:root infrastructure/php-fpm-apache.conf /etc/httpd/conf.d/php-fpm.conf
RUN echo Listen 8080 > /etc/httpd/conf.d/ports.conf

# Set up global cron task
COPY --chown=root:root infrastructure/doomwiki-cron /etc/cron.d/doomwiki-cron
RUN chmod 0644 /etc/cron.d/doomwiki-cron
RUN touch /var/log/cron.log

# Add the base user (no root allowed, but add to sudo)
RUN useradd -s /bin/false -r doomwiki -m
RUN usermod -aG wheel doomwiki
# Also allow the doomwiki user to run commands that many manipulate files
# directories maintained by apache.
RUN usermod -aG apache doomwiki
RUN echo -e "doomwiki\tALL=(ALL)\tNOPASSWD: ALL" > /etc/sudoers.d/020_sudo_for_local
# RUN find / -perm /6000 -type f -exec chmod a-x {} \; || true

# Open up some system locations to allow operation on a read-only filesystem
RUN chmod 777 -R /etc/httpd/
RUN chmod 777 -R /etc/php.d/
RUN chmod 777 -R /var/log/
RUN chmod 777 -R /tmp/

# Allow ECS Exec to work in a read-only container
RUN mkdir -p /var/lib/amazon
RUN chmod 777 /var/lib/amazon

RUN mkdir -p /var/log/amazon
RUN chmod 777 /var/log/amazon

USER doomwiki
WORKDIR /home/doomwiki

RUN mkdir -p public_html

# Install cron task
RUN crontab /etc/cron.d/doomwiki-cron

# Install Composer
RUN mkdir .composer
WORKDIR /home/doomwiki/.composer
RUN wget https://getcomposer.org/installer && php installer --filename=composer
ENV PATH=/home/doomwiki/.composer:$PATH

# Copy support scripts
WORKDIR /home/doomwiki
COPY --chown=doomwiki:doomwiki ./scripts/start-server.sh ./start
COPY --chown=doomwiki:doomwiki ./scripts/doomwiki-jobs.sh ./doomwiki-jobs
COPY --chown=doomwiki:doomwiki ./.env .

# Copy application source code (until we get dynamic installation working)
COPY --chown=doomwiki:doomwiki ./app/. ./public_html/

# Set permissions
RUN chmod 755 -R ./

# Start php-fpm and apache httpd on container run
CMD [ "/home/doomwiki/start" ]

##########################
# Multistage DEPLOY stage
##########################
# Build a deployable version of the container that copies only custom code and
# then builds the project. This stage could also be further subdivided if we
# wanted a final deploy artifact image to exclude composer and other tools.
FROM base AS deploy

# Install doomwiki (and associated packages)
WORKDIR /home/doomwiki

USER root
#COPY --chown=doomwiki:doomwiki ./app/composer.* .
#RUN composer install --no-dev
#ENV PATH=/home/doomwiki/public_html/w:$PATH

# Copy specific doomwiki customizations

# Copy google search console verification file
COPY --chown=doomwiki:doomwiki ./google*.html ./public_html/

# Symlink to EFS Volume
RUN ln -s /var/www/images ./public_html/w/

USER doomwiki
WORKDIR /home/doomwiki/public_html

VOLUME /home/doomwiki/ /tmp/ /var/run/ /etc/ /var/lib/amazon/ /var/log/amazon/

# TODO. Consider other build tools, such as SASS compilation with node lib.

#######################
# Multistage DEV stage
#######################
# Build a development-friendly version of the container that does not copy-in
# any app sources and instead depends on volume maps to bring the needed content
# into the container while still making it editable outside the container.
FROM base AS dev

USER root
# While most development will happen on mapped volumes via an IDE, it can be
# useful to have some additional dev tools available inside the container.
RUN yum -y install \
    vim \
    mariadb105 && \
    yum clean all && \
    ln -s /usr/bin/vim /usr/bin/vi

# The dev container should support xdebug, but it does not necessarially need
# to be installed by default as that adds some extra complexity and overhead
# that not all devs will need. Instead we make a script available for it to
# be installed when needed in a running container.
WORKDIR /home/doomwiki
COPY --chown=doomwiki:doomwiki ./scripts/install-xdebug.sh ./install-xdebug

# Use some dev-specific configurations and scripts.
COPY --chown=doomwiki:doomwiki ./scripts/start-server.dev.sh ./start

# (Re)set permissions.
RUN chmod 755 -R .

# Prepare CLI environment for dev.
ENV PATH=/home/doomwiki/public_html/w:$PATH
WORKDIR /home/doomwiki/public_html
