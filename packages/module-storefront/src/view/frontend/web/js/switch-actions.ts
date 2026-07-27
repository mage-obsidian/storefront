/**
 * Entry point for the header store/currency switchers, loaded by
 * html/header/switcher.twig. Module scripts run with the document already
 * parsed, so the listeners can be attached straight away.
 */
import { bindSwitchers, browserDeps } from "MageObsidian_Storefront::js/switcher";

bindSwitchers(document, browserDeps);
