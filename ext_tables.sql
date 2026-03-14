CREATE TABLE tx_mpcrss_domain_model_feed (
    tt_content int(11) unsigned DEFAULT '0' NOT NULL,

    title varchar(255) DEFAULT '' NOT NULL,
    feed_url text NOT NULL,
    source_name varchar(100) DEFAULT '' NOT NULL,
    description text,

    KEY tt_content (tt_content)
);

CREATE TABLE tt_content (
    tx_mpcrss_feeds int(11) unsigned DEFAULT '0' NOT NULL
);
