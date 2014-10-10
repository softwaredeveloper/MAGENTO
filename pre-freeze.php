<?php
/**
 *  pre-freeze.php
 *
 *  Script that is automatically started by the codeFreeze command
 *  right before the code is frozen. This script updates the version
 *  number in the XML files
 * 
 *  @author Emiel Bruijntjes <emiel.bruijntjes@copernica.com>
 *  @copyright 2014 Copernica BV
 *  @documentation private
 */
require_once('PxFramework/autoload.php');

/**
 *  Check usage
 */
if ($argc < 2) die("usage: pre-freeze.php <version>\n");

/**
 *  The new version number
 *  @var string
 */
$version = $argv[1];

/**
 *  The XML file that we need to store the version number in
 *  @var string
 */
$xmlfile = __DIR__.'/magento/app/code/community/Copernica/Integration/etc/config.xml';

/**
 *  Turn the xml file in a SimpleXML object structure
 *  @var PxSimpleXmlElement
 */
$xml = new PxSimpleXmlElement(file_get_contents($xmlfile));

/**
 *  Update version number
 */
$xml->modules->Copernica_Integration->version = $version;

/**
 *  Store file contents with updated version number
 */
file_put_contents($xmlfile, $xml->asXML());
