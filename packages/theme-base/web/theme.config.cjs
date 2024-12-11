const defaultTheme = require(getPathFromNodeModules('tailwindcss/defaultTheme'));
const formsPlugin = require(getPathFromNodeModules('@tailwindcss/forms'));
const typographyPlugin = require(getPathFromNodeModules('@tailwindcss/typography'));

module.exports = {
    tailwind: {
        theme: {
            extend: {
                ...defaultTheme,
            },
            colors: {
                primary: 'rgb(var(--color-primary))',
                secondary: 'rgb(var(--color-secondary))',
                tertiary: 'rgb(var(--color-tertiary))',
                accent: 'rgb(var(--color-accent))',
                background: 'rgb(var(--color-background))',
                surface: 'rgb(var(--color-surface))',
                border: 'rgb(var(--color-border))',

                'text-primary': 'rgb(var(--color-text-primary))',
                'text-secondary': 'rgb(var(--color-text-secondary))',
                'text-muted': 'rgb(var(--color-text-muted))',

                link: 'rgb(var(--color-link))',
                'link-hover': 'rgb(var(--color-link-hover))',
                'link-visited': 'rgb(var(--color-link-visited))',

                success: 'rgb(var(--color-success))',
                warning: 'rgb(var(--color-warning))',
                error: 'rgb(var(--color-error))',
                info: 'rgb(var(--color-info))',

                'primary-hover': 'rgb(var(--color-primary-hover))',
                'secondary-hover': 'rgb(var(--color-secondary-hover))',
            }
        },
        plugins: [
            formsPlugin,
            typographyPlugin,
            function ({addVariant}) {
                addVariant('group-optional', ':merge(.group):optional &');
                addVariant('peer-optional', ':merge(.peer):optional ~ &');
            }
        ],
        content: [
            './**/*.{vue,js}',
            '../**/*.phtml',
            '../**/*.xml',
            '../*/page_layout/override/base/*.xml',
        ]
    },
    includeParentThemes: false,
    ignoredCssFromModules: [],
    ignoredTailwindConfigFromModules: [],
    exposeNpmPackages: [
        {
            package: 'pinia',
            exposePath: 'pinia',
        }
    ],
}
