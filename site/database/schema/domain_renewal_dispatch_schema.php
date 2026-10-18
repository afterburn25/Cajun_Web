<?php
declare(strict_types=1);
if(!defined('CWP_BOOT')){http_response_code(404);exit;}
/** Purpose/approval witnesses only; invoices, receipts and provider operations retain their owners. */
function domain_renewal_dispatch_schema(string $prefix):array {
    if(!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{1,19}\z/',$prefix))throw new InvalidArgumentException('Invalid prefix.');
    $id='CHAR(32) CHARACTER SET ascii COLLATE ascii_bin';$hash='CHAR(64) CHARACTER SET ascii COLLATE ascii_bin';$text='LONGTEXT CHARACTER SET ascii COLLATE ascii_bin';$tail=' ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin';
    return [
      'domain_renewal_invoices'=>'CREATE TABLE `'.$prefix.'domain_renewal_invoices` (id '.$id.' PRIMARY KEY,invoice_id INT UNSIGNED NOT NULL,binding_id '.$id.' NOT NULL,request_id '.$id.' NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,document_json '.$text.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,UNIQUE KEY renewal_invoice_identity(invoice_id),UNIQUE KEY renewal_invoice_binding(binding_id),INDEX renewal_invoice_request(request_id),FOREIGN KEY (invoice_id) REFERENCES `'.$prefix.'studio_invoices` (id) ON DELETE RESTRICT,FOREIGN KEY (binding_id) REFERENCES `'.$prefix.'domain_renewal_agreements` (id) ON DELETE RESTRICT)'.$tail,
      'domain_renewal_dispatches'=>'CREATE TABLE `'.$prefix.'domain_renewal_dispatches` (id '.$id.' PRIMARY KEY,allocation_id '.$id.' NOT NULL,request_id '.$id.' NOT NULL,grant_seq BIGINT UNSIGNED NOT NULL,profile_id '.$id.' NOT NULL,domain_id BIGINT UNSIGNED NOT NULL,domain VARCHAR(253) NOT NULL,client_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED NOT NULL,document_json '.$text.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,UNIQUE KEY renewal_dispatch_allocation(allocation_id),UNIQUE KEY renewal_dispatch_request(request_id),UNIQUE KEY renewal_dispatch_seq(grant_seq),INDEX renewal_dispatch_domain(domain),FOREIGN KEY (id) REFERENCES `'.$prefix.'provider_operation_grants` (id) ON DELETE RESTRICT,FOREIGN KEY (allocation_id) REFERENCES `'.$prefix.'domain_renewal_invoices` (id) ON DELETE RESTRICT)'.$tail,
      'domain_renewal_dispatch_ends'=>'CREATE TABLE `'.$prefix.'domain_renewal_dispatch_ends` (id '.$id.' PRIMARY KEY,actor_id INT UNSIGNED NOT NULL,disposition VARCHAR(12) NOT NULL,ledger_sha256 '.$hash.' NOT NULL,document_json '.$text.' NOT NULL,document_sha256 '.$hash.' NOT NULL,proof '.$hash.' NOT NULL,FOREIGN KEY (id) REFERENCES `'.$prefix.'domain_renewal_dispatches` (id) ON DELETE RESTRICT)'.$tail,
    ];
}
