FROM ggmartinez/laravel:php-82

RUN yum -y install epel-release && \
    rpm --import http://li.nux.ro/download/nux/RPM-GPG-KEY-nux.ro && \
    rpm -ivh http://li.nux.ro/download/nux/dextop/el7/x86_64/nux-dextop-release-0-1.el7.nux.noarch.rpm && \
    yum -y install ffmpeg ffmpeg-devel

RUN sed -i 's/^post_max_size = .*/post_max_size = 500M/' /etc/php.ini && \
    sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 500M/' /etc/php.ini && \
    sed -i 's/^memory_limit = .*/memory_limit = 512M/' /etc/php.ini && \
    sed -i 's/^max_execution_time = .*/max_execution_time = 300/' /etc/php.ini && \
    sed -i 's/^max_input_time = .*/max_input_time = 300/' /etc/php.ini