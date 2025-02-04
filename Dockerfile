FROM trafex/php-nginx:3.6.0
USER root
RUN apk add imagemagick
USER nobody
COPY nginx.conf /etc/nginx/conf.d/default.conf