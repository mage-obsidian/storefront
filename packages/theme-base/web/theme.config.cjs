const defaultTheme = require(getPathFromNodeModules('tailwindcss/defaultTheme'));
const formsPlugin = require(getPathFromNodeModules('@tailwindcss/forms'));
const typographyPlugin = require(getPathFromNodeModules('@tailwindcss/typography'));

module.exports = {
    tailwind: {
        theme: {
            extend: {
                ...defaultTheme,
            },
        },
        plugins: [formsPlugin, typographyPlugin],
        content: [
            './**/*.{vue,js}',
            '../**/*.phtml',
            '../*/page_layout/override/base/*.xml',
        ],
    },
    includeParentThemes: false,
    ignoredCssFromModules: [],
    ignoredTailwindConfigFromModules: [],
    exposeNpmPackages: [
        {
            package: 'pinia',
            exposePath: 'my-pinia',
        }
    ],
}
