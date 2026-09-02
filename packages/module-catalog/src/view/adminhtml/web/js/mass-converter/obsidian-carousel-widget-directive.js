define([
    'Magento_PageBuilder/js/content-type/products/mass-converter/carousel-widget-directive',
    'Magento_PageBuilder/js/utils/object'
], function (CarouselWidgetDirective, object) {
    'use strict';

    var CORE_TEMPLATE = 'Magento_PageBuilder::catalog/product/widget/content/carousel.phtml',
        OBSIDIAN_TEMPLATE = 'Magento_CatalogWidget::product/widget/content/carousel.twig';

    function ObsidianCarouselWidgetDirective() {
        return CarouselWidgetDirective.apply(this, arguments) || this;
    }

    ObsidianCarouselWidgetDirective.prototype = Object.create(CarouselWidgetDirective.prototype);
    ObsidianCarouselWidgetDirective.prototype.constructor = ObsidianCarouselWidgetDirective;

    ObsidianCarouselWidgetDirective.prototype.toDom = function (data, config) {
        var result = CarouselWidgetDirective.prototype.toDom.call(this, data, config),
            directive = object.get(result, config.html_variable);

        if (typeof directive === 'string' && directive.indexOf(CORE_TEMPLATE) !== -1) {
            object.set(result, config.html_variable, directive.replace(CORE_TEMPLATE, OBSIDIAN_TEMPLATE));
        }

        return result;
    };

    return ObsidianCarouselWidgetDirective;
});
