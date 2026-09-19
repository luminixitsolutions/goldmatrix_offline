-- Registry and branch databases: optional location / access metadata for each tbl_branches row.
-- ip_address: IPv4/IPv6 for the server or public endpoint (optional).
-- subdomain_url: full host, e.g. pune.goldmatrix.com (optional). Host-based routing still matches tbl_branches.code to the first label (PUNE).
-- Run on each MySQL schema that has tbl_branches, or open Branches in admin (auto-ALTER when possible).

ALTER TABLE `tbl_branches`
  ADD COLUMN `ip_address` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Branch / server public IP (optional)';

ALTER TABLE `tbl_branches`
  ADD COLUMN `subdomain_url` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Branch host, e.g. pune.goldmatrix.com (optional)';
