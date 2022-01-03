# Changelog

- Added DiscordRoleFilter feature and settings

## 5.0.0
- Replace the API Throttler by a Guzzle Middleware which is more efficient
- Make driver compatible with connector 2.0.x
- Improve error logging

## 4.0.0
- Switch to an universal SeAT Connector layout : `warlof/seat-connector`
- A data conversion is available with `php artisan seat-connector:convert:discord`. You will have to setup the connector into `Connector > Setup`
- Permission `discord-connector:view` has been replaced by `seat-connector:view`
- Permission `discord-connector:security` has been replaced by `seat-connector:security`
- Permissions `discord-connector:create` and `discord-connector:setup`
- In case you're encountering issue, please open a new ticker [here](https://github.com/warlof/seat-connector/issues)
- In case you have some trouble with the early 4.x generation, you can stick to previous 3.x using `composer require warlof/seat-discord-connector:^3.3`

## 3.1.2
- Address an issue which was preventing to sort properly values into User Mapping table [#19](https://github.com/warlof/seat-discord-connector/issues/19)
- Address an issue which was preventing to search values into User Mapping table [#18](https://github.com/warlof/seat-discord-connector/issues/18)
- Address a security issue which was allowing everyone to get access to the plugin (server join only) [#17](https://github.com/warlof/seat-discord-connector/issues/17)
- Address an issue which was resulting in a kick from SeAT unbind user which should not be the case by design

## 3.0.0-beta2
- Address some UI issues and update [documentation](https://github.com/warlof/seat-discord-connector/blob/master/README.md).

## 3.0.0-beta1
- This is a release candidate. Use at your risk. Please report any issues and wishes on [github](https://github.com/warlof/seat-discord-connector/issues).
