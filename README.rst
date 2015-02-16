GithubComment
=============

Why?
----

At SensioLabs, we deploy often, and when we deploy we want to notify developer
what PR is deployed. Add this tool to your deploy chain to automatically add a
new comment on each deployed PR.

How?
----

Just grab the latest phar_. and start using it.

Usage?
------

    php github-comment.phar "This PR is deployed to Staging" HEAD~10

This tool try to guess repository information. If the repository information can
not be guess you can use ``--organization`` and ``--repository`` option:

    php github-comment.phar "This PR is deployed to Staging" HEAD~10 --organization=lyrixx --repository=GithubComment

A commit range can also be specified:

    php github-comment.phar "This PR is deployed to Prod" HEAD~10 origin/prod

``dry-run`` option can be used to skip comment adding:

    php github-comment.phar "This PR is deployed to Staging" HEAD~10 --dry-run

This tool will ask a confirmation before sending comment to github, Use ``no-
confirmation`` option to skip confirmation.

    php github-comment.phar "This PR is deployed to Staging" HEAD~10 --no-confirmation

.. _phar: https://github.com/lyrixx/GithubComment/releases
