CREATE TABLE tx_mpcrss_domain_model_feed (
    tt_content int(11) unsigned DEFAULT '0' NOT NULL,

    title varchar(255) DEFAULT '' NOT NULL,
    feed_url text NOT NULL,
    source_name varchar(100) DEFAULT '' NOT NULL,
    description text,

    KEY tt_content (tt_content)
);

CREATE TABLE tt_content (
    tx_mpcrss_feeds int(11) unsigned DEFAULT '0' NOT NULL,
    tx_mpcrss_default_category varchar(255) DEFAULT '' NOT NULL,
    tx_mpcrss_grouping_mode varchar(20) DEFAULT 'category' NOT NULL,
    tx_mpcrss_include_categories varchar(255) DEFAULT '' NOT NULL,
    tx_mpcrss_exclude_categories varchar(255) DEFAULT '' NOT NULL,
    tx_mpcrss_max_items int(11) DEFAULT '9' NOT NULL,
    tx_mpcrss_cache_lifetime int(11) DEFAULT '1800' NOT NULL,
    tx_mpcrss_show_filter smallint(5) unsigned DEFAULT '1' NOT NULL,
    tx_mpcrss_paginate smallint(5) unsigned DEFAULT '0' NOT NULL,
    tx_mpcrss_items_per_page int(11) DEFAULT '10' NOT NULL
);
