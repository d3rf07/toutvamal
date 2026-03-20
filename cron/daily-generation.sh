#!/bin/bash
# ToutVaMal.fr - Daily article generation
# Generates 3 articles per day at different times

cd /home/u443792660/domains/toutvamal.fr/public_html
/usr/bin/php cron/generate-articles.php 1 --publish >> /tmp/toutvamal_cron.log 2>&1
