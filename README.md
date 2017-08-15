# salt-php-sso
SSO (Single Sign On) for SALT framework

## Features
* XHTML strict compatible
* Authentication from multiple sources (LDAP, Database, User PHP class, SSO account)
* User profile with security options and application credentials
* Users and credentials stored in MySQL database
* User can request access to an application which he don't have access
* Client application : Call an SSO method before each page
* Client application : Write an Handler class for simulate authentification in application avec SSO login.
* Client application : SSO menu can be displayed, with multiple themes

## Requirements
* Each client application have to be in the same web server. If not, we have to create a new Virtual Host in web server configured as a proxy for the application.
* Each client application don't have to open a session before calling SSO if the session have a special close handler (session\_set\_save\_handler)

### Documentation 
[https://salt-php.org/modules/sso/](https://salt-php.org/modules/sso/) in french
