Which Magento plugin to choose?
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

There are a number of different Magento plugins. We know about the
following ones:

* On MagentoConnect

    The are two plugins available on Magento connect: a Copernica-Magento
    plugin that is being maintained by "Cream", a company based in The
    Netherlands. This plugin uses the Copernica REST API to create and
    update Copernica profiles when events occur (orders, add-to-basket,
    etc). There is no public source repository (like on GitHub) for
    this plugin. This is a different plugin than the one you're now
    looking at!

    The other plugin available on MagentoConnect is the brand new plugin
    developed by Copernica - and this is the one that you're now looking 
    at. This plugin uses a custom-for-Magento API to communicate between 
    Copernica and Magento. At this point in time, this plugin is still in 
    development and can not yet be used in production. The latest version 
    of the source code for this new plugin can be found on Github:

        https://github.com/CopernicaMarketingSoftware/MAGENTO
        
* On GitHub

    This plugin can also be found on GitHub. If you download the plugin
    from Magento and not from Github, you get the bleeding edge, latest 
    version of the plugin.

        https://github.com/CopernicaMarketingSoftware/MAGENTO


Directory structure
~~~~~~~~~~~~~~~~~~~
* build.sh:     script that turns the Copernica Magento extension into
                a .zip file that can be uploaded to MagentoConnect.com.

* magento:      Directory that contains the actual extension. This
                directory has exactly the same structure as a normal
                Magento installation, so the extension can be copied
                into a running Magento installation.

Registering the extension
~~~~~~~~~~~~~~~~~~~~~~~~~
When Magento starts, it loads all *.xml files from the app/etc/modules
directory to find out which modules are installed. We have added one
extra file to this directory, Copernica_Integration.xml. It contains
the settings, and a list of dependencies.

Templates
~~~~~~~~~
The Copernica Magento integration does not add a lot of things to the
Magento user interface, so there are not many templates to be explained.

In app/design/adminhtml (and even a couple subdirectories deeper, of
which we do not exactly understand the structure) you can find two
template files:

    export.phtml
    ~~~~~~~~~~~~
    a page to start synchronizing all _old_ data from Magento to
    Copernica. This export page can be used when the customer first
    installs the extension. After the  initial synchronization this page
    is not of much use because from that moment on the synchronization
    happens in (almost) real-time (every five minutes).

    The page also shows the current status of synchronizing if a
    synchronization is in progress.

    For synchronizing there is no risk of race conditions. If a
    synchronization is started a couple of times in a row, the worst
    thing that can happen is that data in Copernica is overwritten with
    exactly the same data. Copernica does not assign new ID's or creates
    new records when data is synchronized that was already synchronized
    before.

    settings.phtml
    ~~~~~~~~~~~~~~
    Page to link the Magento webshop to a Copernica account.


The actual code
~~~~~~~~~~~~~~~
The actual code can be found in the app/code/community/Copernica/Integration
directory. It contains the following files:

    etc/adminhtml.xml
    ~~~~~~~~~~~~~~~~~
    XML file responsible for adding the "Copernica" tab to the Magento
    admin page.

    etc/config.xml
    ~~~~~~~~~~~~~~
    This is an interesting file. It contains a list of "models" or
    "entities" that are defined by the extension.

    Next to that, it lists all the events to which the extension
    responds. Every event that we are interested in, will be picked up
    by a "integration_observer" class.

    Inside this XML file we also set the crontab that will run every
    five minutes to synchronize the queue with Copernica.

    sql/integration_setup
    ~~~~~~~~~~~~~~~~~~~~~
    Files that run once when Magento is installed. It creates the SQL tables
    that are needed by the extension.

    Block/Adminhtml/Integration
    ~~~~~~~~~~~~~~~~~~~~~~~~~~~
    We do not really understand these files. They are somehow needed in
    combination with the *.phtml files that can be found in a completely
    different branche of the Magento installation. Luckily, our extension
    does not have a lot of user interface code, so the "Block" classes
    are almost empty.

    Controller/Base.php
    ~~~~~~~~~~~~~~~~~~~
    Base class for the controller objects that you'll find somewhere
    else. This is the base class for other controller classes (we'll
    describe these later).

    This controller base class, and the other controller classes, are
    _only_ used for displaying the admin pages of our extension. The
    base class runs some checks like whether the extension is correctly
    linked to Copernica, and whether there is a queue or not, and make
    sure that an error message is displayed if anything is wrong.

    controller/.../*.php
    ~~~~~~~~~~~~~~~~~~~~
    Objects that handle the admin pages. In Copernica world, we would call
    this Components with initParser() and processForm() methods, in Magento
    land this is slightly different, but the idea is the same.

    Model/QueueProcessor.php
    ~~~~~~~~~~~~~~~~~~~~~~~~
    Class that is started by the crontab, and that reads all actions from the
    queue, and sends them to Copernica.

    Model/SyncProcessor.php
    ~~~~~~~~~~~~~~~~~~~~~~~
    Class that is executed by 'start_sync' task. Its responsibility is to
    synchronize old data in reasonable time chunks.

    Model/Queue.php
    ~~~~~~~~~~~~~~~
    Note: this is _not_ the queue, but one _item_ from the queue. It has
    one important method, "getObject()", that returns the object that
    should be synchronized.

    Model/ErrorQueue.php
    ~~~~~~~~~~~~~~~~~~~~
    One item from the queue that failed to synchronize.

    Model/Config.php
    ~~~~~~~~~~~~~~~~
    Configuration file utility. We copied this from some other location, so
    we do not exactly understand what is going on here.

    Model/Observer.php
    ~~~~~~~~~~~~~~~~~~
    Remember the config.xml file we described before? It contains a reference
    to this observer class for all the event handling. This class has a list
    of methods that are called by Magento when an event occurs. Every one
    of these methods will add a record to the queue.

    Model/Mysql4/*
    ~~~~~~~~~~~~~~
    We do not understand this directory. But it seems to be necessary to
    create simple classes with ugly names for each entity that was added
    to Magento.

    Helper/Api.php
    ~~~~~~~~~~~~~~
    The classes inside "Helper" are singletons. It does not make any sense
    to have multiple Api objects, so we put that file in this Helper dir.

    Helper/RESTRequest.php
    ~~~~~~~~~~~~~~~~~~~~~~
    Class that wraps a REST request. This class also contains the address
    of the Copernica API.

The deployment
~~~~~~~~~~~~~~
To deploy extension on connect platform it's essential to have a working magetno
installation. It's wise to use newest possible version. Follow this steps to
deploy magento extension:

1.  Ensure that magento installation that will be used to deploy extension
    contains desired extension version.

2.  Copy Copernica.xml to {magento}/var/connect.

3.  On admin panel go to System > Magento Connect > Package Extension. Go to
    "Load local package" and pick "Copernica". Package form should be filled
    with correct data.

    NOTE: When directory structure changes between releases it's imperative to
    check if all files are included in Contents tab.

4.  Update version and release info in "Release info" tab and hit "Save Data and
    Create Package" button. A Copernica-{version}.tgz file should be created.
    This file should be uploaded on magento connect website. After upload is
    completed new extension version will be available via connect platform.



