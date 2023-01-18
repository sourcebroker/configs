TYPO3 Extension configs
=======================

.. contents:: :local:

What does it do?
----------------

This extension include PHP files based on TYPO3_CONTEXT value. This allows you to make fine tuning of
TYPO3 settings based on different context: instance, feature activation, system verbosity etc.


Installation
------------

1. Install using composer:

   ::

    composer require sourcebroker/configs

2. Add following code in ``typo3conf/AdditionalConfiguration.php``

   ::

    <?php

    defined('TYPO3') or die();

    \SourceBroker\ConfigTypo3\Config::initialize()
        ->appendContextToSiteName()
        ->includeContextDependentConfigurationFiles();

3. Create folder ``context`` in folder ``config``.

4. In folder ``context`` create folders that starts with 1\_*, 2\_*, 3\_*, 4\_*., example ``1_verbosity``, ``2_mode``,
   ``3_instance``

5. Inside those folders create php files with the names you will use in TYPO3_CONTEXT.

Example
-------

1. Copy example configs from ``Resources/Private/Examples/Example1/context`` to folder ``config/context``

2. You can change the folder names ``1_verbosity``, ``2_mode``, ``3_instance`` to whatever you want but
   do not change the ``number_underscore`` pair. Numbers decide about what part of configs to read from the
   folders based on TYPO3_CONTEXT parts. Part after number and underscore play no role.

3. Change TYPO3_CONTEXT to ``Development/Staging/Beta`` then file ``Development.php`` will be included from folder
   ``1_verbosity``, file ``Staging.php`` will be included from folder ``2_mode`` and file ``Beta.php`` will be included
   from folder ``3_instance``.

You can have as many folders with numbers as you like, for example you can set TYPO3_CONTEXT to
``Production//Live/Feature1`` then corresponding files will be included from folders 1\_*, 2\_*, 3\_*, 4\_*.

If you install package ``helhum/dotenv-connector`` then you additionally have possibility to modify
``$GLOBALS['TYPO3_CONF_VARS']`` array by adding entries in ``.env`` file with convention:
``TYPO3__[first_level_array]__[second_level_array]__[third_level_array] = "value"``

This allows you to provide database values per instance by putting following lines to .env files of each instance.
The ``.env`` file should be out of git.

Example:

 ::

    TYPO3__DB__Connections__Default__dbname=".."
    TYPO3__DB__Connections__Default__host=".."
    TYPO3__DB__Connections__Default__port=".."
    TYPO3__DB__Connections__Default__user=".."
    TYPO3__DB__Connections__Default__password=".."

    TYPO3__GFX__processor_path=".."
    TYPO3__GFX__processor_path_lzw=".."
    TYPO3__GFX__processor_colorspace=".."


It is up to you to decide what values to put inside ``.env`` (which is out of git) and which
to ``config/context/3_instance/Live.php``, ``config/context/3_instance/Beta.php`` etc which are inside git.

Lot of values stored in ``.env`` file means that it is harder to recreate the same TYPO3 state on local development system.
If those settings are in git then you can just switch TYPO3_CONTEXT from local ``TYPO3_CONTEXT=Development/Staging/Beta``
to beta ``TYPO3_CONTEXT=Development/Staging/Beta`` on local development system.

Database access data are good candidate to be put inside .env.


Inspiration
-----------

* Extension https://github.com/b13/typo3-config