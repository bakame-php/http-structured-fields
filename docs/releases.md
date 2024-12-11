---
layout: default
title: Release Notes
redirect_from:
    - /changelog/
    - /upgrading/
---

# Release Notes

These are the release notes from `bakame/http-structured-fields`. We've tried to cover all changes,
including backward compatible breaks from the first commit through to the current stable release.
If we've missed anything, feel free to create an issue, or send a pull request.

{% for release in site.github.releases %}

## {{ release.name }} - {{ release.published_at | date: "%Y-%m-%d" }}

{{ release.body | replace:'```':'~~~' | markdownify }}
{% endfor %}
