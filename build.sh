#!/bin/bash

# remove old magento extension
rm magento_ext.zip

# we want to zip whole dir but exclude .svn directories
zip -r magento_ext.zip magento --exclude=*.svn* 
