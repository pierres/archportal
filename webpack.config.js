var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('web/build/')
    .setPublicPath('/build')
    .cleanupOutputBeforeBuild()

    .addEntry('js/start', './assets/js/start.js')
    .addEntry('js/packages', './assets/js/packages.js')
    .addEntry('js/mirrors', './assets/js/mirrors.js')
    .addEntry('js/packagers', './assets/js/packagers.js')
    .createSharedEntry('js/vendor', [
        'jquery',
        'popper.js',
        'bootstrap',
        'datatables.net',
        'datatables.net-bs4',
        './assets/js/lang-loader!datatables.net-plugins/i18n/German.lang'
    ])
    .addStyleEntry('css/app', './assets/css/app.scss')
    .addStyleEntry('images/archicon', './assets/images/archicon.svg')
    .addStyleEntry('images/archlogo', './assets/images/archlogo.svg')

    .enableSassLoader()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning()
    .enablePostCssLoader()
    .autoProvidejQuery()
    .autoProvideVariables({
        'Popper': 'popper.js'
    })
;

module.exports = Encore.getWebpackConfig();