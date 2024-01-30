This is an example of Ivan Faizulin code.
It is a big module from real project where removed a 99% of code not related to the current functionality.

The main idea of module is to send notifications for users who not logged to site last X months.
Also, need to delete such inactive users after some period of time.

The solution implemented a queue for such users and worker to process items.
Queue filling and processing via cron jobs followed by drupal way.

As user, you can go to configuration page and set up the notification text and periods. 
Please check .yml file in the root folder for example.
