define([], function () {
    'use strict';

    function IslandProps() {}

    IslandProps.prototype.fromDom = function (value) {
        if (!value) {
            return {};
        }

        try {
            return JSON.parse(value);
        } catch (error) {
            return {};
        }
    };

    IslandProps.prototype.toDom = function (name, data) {
        var props = {};

        Object.keys(data).forEach(function (key) {
            if (key.indexOf('prop_') !== 0) {
                return;
            }

            var value = data[key];

            if (value === '' || value === null || value === undefined) {
                return;
            }

            props[key.slice('prop_'.length)] = value;
        });

        return JSON.stringify(props);
    };

    return IslandProps;
});
