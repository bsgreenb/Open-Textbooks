## Installation/Configuration

Installing is as simple as downloading the code to your webserver, and importing the structure+data .sql file.  This file has over 1,000 working schools for you to begin with.  Note that the Follett bookstore schools will require some extra work to start getting complete data from (see Documentation).  You can add any of the missing 2-3k schools with online bookstores by following the steps in the documentation Wiki.

There are two necessary config files for the code to run, db_config.php and proxy_config.php.  Both are derived from their respective *_template.php files.  db_config just requires the credentials for logging into your MySQL dataqbase.  proxy_config requires proxies to use for scraping the Follett and Barnes and Nobles, respectively.

You'll see in the content/ folder that I included api.php.  That's a REST API that you can use to verify that the scraping is working correctly.  See the comments at the top of that file for the GET parameters it takes.  I also included runthrough.php, which runs through the api and outputs any schools where the scrapers are non-functional.

## Documentation

See the [Wiki](https://github.com/bsgreenb/Open-Textbooks/wiki) which explains clearly how all the scrapers work, and how they avoid the anti-scraping techniques employed by the 6 major bookstore website technologies.  Additionally, it explains how to add new schools for every bookstore type. I've also added a section on the legality of the scrapers.

## Open-Source Volunteering Opportunities

There's a lot of ways this project could be improved.  Some of these tasks don't even require coding skill.

* Add the schools that are missing from the initial database
* Translate this library into another language, esp. Python or Ruby
* Help keep the scrapers up to date, as the bookstore software changes every couple of years.
* Find any bugs in the code.  I'm sure there are some, and there are definitely lots of places where the code can be simplified/improved. 

## Posting bugs/issues

Please try and provide this info:

1. Verification that your proxy config is working. 
2. A complete description of when the bug happens.   Does it happen with all schools, just schools of this type,  or does it only happen for a particular set of schools?  Does the issue depend on whether you access the data with the browser vs. my code?  Does the issue depend on whether your proxy is enabled?
3. Complete cURL HTTP request/response logs and side-by-side complete browser HTTP request/response logs (from Chrome Dev Tools or Firebug).

## Additional Resources

* Getchabooks has open-sourced their code base, which includes a complete textbook comparison site with scraping of Barnes and Nobles and Follett stores.  https://github.com/getchabooks/getchabooks
