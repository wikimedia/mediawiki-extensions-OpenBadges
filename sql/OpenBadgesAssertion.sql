--
-- OpenBadges logging schema
-- Records relevant information regarding issuing of badges
--
--

CREATE TABLE /*_*/openbadges_assertion (
  obl_id int NOT NULL PRIMARY KEY auto_increment,

  -- Timestamp
  obl_timestamp binary(14) NOT NULL,

  -- User id of the receiver
  obl_receiver int(10) unsigned NOT NULL REFERENCES user(user_id),

  -- URL of the badge for the receiver
  obl_badge_id int NOT NULL REFERENCES openbadges_class(obl_badge_id),

  -- Evidence for receiving the badge, if any
  obl_badge_evidence varchar(255),

  -- Expiration of the badge, if any
  obl_expiration binary(14)
) /*wgDBTableOptions*/;

CREATE INDEX /*i*/obl_timestamp ON /*_*/openbadges_assertion (obl_timestamp);
CREATE INDEX /*i*/obl_receiver ON /*_*/openbadges_assertion (obl_receiver);
