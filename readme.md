# Action Scheduler Tools

Adds settings to the **Tools ‣ Scheduled Actions** admin page, making it easy to fine-tune various performance characteristics of Action Scheduler.

![](https://share.codingkills.me/demo/wordpress/action-scheduler-config-010.png)

### How Action Scheduler Works

[Action Scheduler](https://actionscheduler.org/) works by periodically spawning queue runners, which have the job of actually processing any waiting actions. To do this, it first claims a batch of eligible actions and, once claimed, those actions cannot be processed by other queue runners (with one exception: if a queue runner fails unexpectedly, the claim will ultimately be released and the actions will then be picked up by a future queue runner). 

By default, Action Scheduler only allows one queue runner to exist at any given moment (this minimizes performance impact, especially for shared hosting environments) and the batch size is limited to 20 actions. Sometimes, depending on the amount of work that needs to be processed, these defaults are a little too modest.

Once actions have been processed, they are automatically deleted *once* they are older than the retention period which, by default, is 30 days. In other words, records are kept even for successfully completed pieces of work, for around one month.

### This Tool

With this plugin, you get a straightforward UI with which you can fine tune the above characteristics. You can also do exactly the same thing with some custom code of your own, but this plugin is arguably easier and, in any case, has a very small footprint. When tweaking the settings, keep the following "principles" in mind:

- A large batch size can be the enemy of concurrency. For example, if you set the batch size to 50, and there are usually less than 50 actions waiting to be processed, then there's probably no point in setting the number of max queue runners to anything above 1.
- A low retention period can help keep the size of Action Scheduler's database tables down ... but it also means you have a shorter period in which to capture information about any actions that are problematic in some way.
