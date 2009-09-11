=== Knowledge Building ===
Contributors: tatti
Donate link: http://fle4.uiah.fi/
Tags: education, learning, knowledge building, progressive inquiry, comments
Requires at least: 2.7
Tested up to: 2.8.4
Stable tag: /trunk/

This plugin enables comments to have knowledge types and facilitates knowledge building on Wordpress.

== Description ==

Knowledge Building is a process of collaboratively building new understanding and knowledge through meaningful discussion. This plugin allows knowledge building processes to happen on Wordpress comments. There are several different knowledge typesets to choose from, and they can be mapped to individual post categories, so other posts will continue to use their normal commenting functionality, while some categories will be equipped with knowledge building tools.

This plugin uses the JQuery javascript library, and the jquery.simpledialog plugin for JQuery to streamline the user interface. JQuery is used in noconflict-mode, so this won't disturb a Wordpress installation that uses another javascript library as its default.

== Installation ==

This section describes how to install the plugin and get it working.

1. Store the plugin into the `/wp-content/plugins/knowledgebuilding/` directory.
1. Activate the plugin through the 'Plugins' menu in Wordpress.
1. Edit the 'Comments' template and change 'wp_comment_list' to 'knbu_comment_list'.
1. Go to Settings, Knowledge Building and select which post Categories should have Knowledge Building enabled.

Please note that since this plugin relies heavily on the commenting feature, not all themes will work nicely. Specifically, this plugin works best with themes that use the Wordpress built-in comment Walker. You can detect this by checking whether or not your comments.php template has a call to 'wp_comment_list' or not. If it does not, then it has its own custom way of showing comments, which this plugin cannot easily work with. You can either select another theme which uses Wordpress Walker, or just try and replace the code in comments.php that displays comments with a call to `knbu_comment_list();` and cross your fingers. :-)

== Frequently Asked Questions ==

= Where can I get more Knowledge Typesets? =

Go to http://fle3.uiah.fi/download.html to find typesets exported from Fle3. Basically you just need to download the zip file, open it, and take the 'fledom.xml' file, rename it something meaningful (like the name of the zip file you downloaded) while retaining the xml extension, and place the file into the kbsets folder of this plugin.

= How can I create new Knowledge Typesets? =

Either copy an existing typeset's XML file to a new name, and edit it to your liking, or use the online editor of Fle3 to create a new set, and export it into an XML file (see previous question).

== Screenshots ==

1. Demonstration of the progressive inquiry knowledge typeset in use on Wordpress.

== Changelog ==

= 0.2 =
* Beta release. Main functionality is done, and seems to be working.

= 0.1 =
* Initial alpha version.

