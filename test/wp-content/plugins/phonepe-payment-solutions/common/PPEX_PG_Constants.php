<?php

/**
 * PPEX_PG_Constants
 */
if (!class_exists('PPEX_PG_Constants')) {
  class PPEX_PG_Constants {
    const PLUGIN_SOURCE_HEADER                  = "plugin";
    const WOOCOMMERCE                           = "woocommerce";

    const PHONEPE_PG_TITLE                      = "PhonePe Payment Solutions";
    const PHONEPE_METHOD_TITLE                  = "PhonePe";
    const PAYMENT_METHOD_NAME                   = "PhonePe Payment Solutions";
    const PHONEPE_PG_ID                         = "phonepe";
    const FLOW_TYPE                             = "B2B_PG";
    const APPLICATION_JSON                      = "application/json";
    const IFRAME                                = "IFRAME";
    const REDIRECT                              = "REDIRECT";

    const PG_V1_PAY_ENDPOINT                    = "/pg/v1/pay";
    const PG_V1_STATUS_ENDPOINT                 = "/pg/v1/status/";
    const INGEST_EVENT_ENDPOINT                 = "/plugin/v1/events";

    const INTERNAL_SECURITY_BLOCK_1             = "INTERNAL_SECURITY_BLOCK_1";
    const INTERNAL_SECURITY_BLOCK_2             = "INTERNAL_SECURITY_BLOCK_2";
    const INTERNAL_SECURITY_BLOCK_4             = "INTERNAL_SECURITY_BLOCK_4";

    const CHECKOUT_ORDER_COMPLETED              = "CHECKOUT_ORDER_COMPLETED";
    const CHECKOUT_ORDER_FAILED                 = "CHECKOUT_ORDER_FAILED";
    const PG_V2_COMPLETED                       = "COMPLETED";
    const PG_V2_FAILED                          = "FAILED";
    const PG_V2_PENDING                         = "PENDING";
		const CLIENT_NOT_FOUND											= "CLIENT_NOT_FOUND";
    const LOADER_GIF                            = "https://imgstatic.phonepe.com/images/online-merchant-assets/plugins/woocommerce/64/64/loader.gif";
  }
}
