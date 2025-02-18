FROM trafex/php-nginx:3.6.0
USER root
RUN apk add imagemagick git
USER nobody
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY php.ini /etc/php83/conf.d/custom.ini