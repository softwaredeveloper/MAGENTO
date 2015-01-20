#!/bin/bash

# get proper script path
SCRIPT=$(readlink -f "$0")

# find out repository base dir
BASEDIR=$(dirname "$SCRIPT")

# remove old extension package (if one exists)
if [ -f $BASEDIR/copernica_magento_extension.zip ] 
then
    rm $BASEDIR/copernica_magento_extension.zip
fi

# we want to zip whole dir but exclude .git directories directories
zip -r $BASEDIR/copernica_magento_extension.zip $BASEDIR/magento --exclude=*.git* > /dev/null
