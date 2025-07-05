# use Ubuntu 22.04 as a basis
FROM ubuntu:22.04

RUN apt-get update
RUN apt-get install -y wget sudo apt-utils

RUN wget -P /tmp http://www.teitok.org/downloads/install-teitok.pl
RUN DEBIAN_FRONTEND="noninteractive" perl /tmp/install-teitok.pl -q

# configure TEITOK for (Montenegro) XML and CoNLL-U
COPY settings.xml /var/www/html/teitok/shared/Resources/settings.xml
RUN chmod a+rw /var/www/html/teitok/shared/Resources/settings.xml

# I tried to set up a configuration where the local directory is mounted to /var/www/html/teitok/shared and pre-populated by TEITOK defaults. 
# This didn't work, so far. Instead, I focus on setting up XML and CoNLL-U processing.
# 
RUN apt-get install -y zip unzip
RUN chmod -R a+rwx /var/www/html/teitok/shared
RUN cd /var/www/html/teitok/shared; zip -r /var/www/html/teitok/shared.zip *
RUN rm -rf /var/www/html/teitok/shared
RUN ln -s /shared /var/www/html/teitok/shared

# setup init script
COPY startup.sh /usr/local/bin/startup
RUN chmod a+rwx /usr/local/bin/startup

EXPOSE 80 443 22
# CMD ["sh", "-c", "unzip -n /var/www/html/teitok/shared.zip -d /shared; chmod -R a+rwx /shared; apachectl -D FOREGROUND >& /shared/apachectl.log;"]
# CMD [ "sh", "-c", "unzip -n /var/www/html/teitok/shared.zip -d /shared; apachectl -D FOREGROUND >& /shared/apachectl.log"]
# CMD ["apachectl","-D","FOREGROUND"]
CMD ["bash", "-e", "/usr/local/bin/startup" ]