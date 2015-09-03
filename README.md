# ChannelManager Module
[![Build Status](https://scrutinizer-ci.com/g/WildPHP/module-channelmanager/badges/build.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-channelmanager/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/WildPHP/module-channelmanager/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-channelmanager/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/wildphp/module-channelmanager/v/stable)](https://packagist.org/packages/wildphp/module-channelmanager)
[![Latest Unstable Version](https://poser.pugx.org/wildphp/module-channelmanager/v/unstable)](https://packagist.org/packages/wildphp/module-channelmanager)
[![Total Downloads](https://poser.pugx.org/wildphp/module-channelmanager/downloads)](https://packagist.org/packages/wildphp/module-channelmanager)

This module keeps track of the channels the bot has joined. It also provides the `join` and `part` commands.

## System Requirements
If your setup can run the main bot, it can run this module as well.

## Installation
To install this module, we will use `composer`:

	composer require wildphp/module-channelmanager

That will install all required files for the module. In order to activate the module, add the following line to your `config.neon`, section `modules`:

	- WildPHP/Modules/ChannelManager/ChannelManager

Make sure to include a tab character in front. The bot will run the module the next time it is started.

## License
This module is licensed under the GNU General Public License, version 3. Please see `LICENSE` to read it.