=== Kohandezh Knowledge ===
Contributors: mohammad-ali-kohandezh
Tags: knowledge, enterprise-ai, quantum, schema, rest-api
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Layer B — Enterprise AI & Quantum Knowledge Platform for kohandezh.com.
Additive and isolated from the personal-brand Layer A. Deactivate = full rollback.

== Description ==

Registers the Knowledge Platform content model (CPTs, taxonomies, post meta,
evidence-status vocabulary), a stable canonical entity ID, and a read-only
REST API at `kohandezh/v1`. Designed per docs/ARCHITECTURE.md and docs/ONTOLOGY.md.

* Content types: knowledge, news, glossary, case-study, research.
* Taxonomies: topic, industry, technology, ai-domain, quantum-domain,
  evidence-status, content-type, news-source.
* Feature flags (wp-config.php): KBK_FEATURE_KNOWLEDGE, KBK_FEATURE_NEWS,
  KBK_FEATURE_GLOSSARY, KBK_FEATURE_CASE, KBK_FEATURE_RESEARCH, KBK_FEATURE_REST.
* REST: GET /wp-json/kohandezh/v1/entities, /entities/{id}, /topics.
  Unverified/disputed/deprecated evidence is never exposed publicly.

This plugin does NOT modify Layer A (homepage, CV pages, blog) and does NOT
flow through the static→theme sync (ADR-0001).

== Installation ==

Upload as a plugin (separate from the theme) and activate. On activation the
rewrite rules flush automatically. See docs/DEPLOYMENT_GATES.md before any
production deploy.

== Changelog ==

= 0.1.0 =
* Initial content model + REST read API (Phase 5 foundation).
