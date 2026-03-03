const path = require('path')
const Encore = require('@symfony/webpack-encore')

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
	Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev')
}

Encore
	// directory where compiled assets will be stored
	.setOutputPath('public/build/')
	// public path used by the web server to access the output path
	.setPublicPath('/build')
	// only needed for CDN's or subdirectory deploy
	//.setManifestKeyPrefix('build/')

	.copyFiles({
		from: './assets/images',
		to: 'images/[path][name].[ext]',
	})

	/*
	 * ENTRY CONFIG
	 *
	 * Each entry will result in one JavaScript file (e.g. app.js)
	 * and one CSS file (e.g. app.css) if your JavaScript imports CSS.
	 */
	.addEntry('app', './assets/app.js')

	// When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
	.splitEntryChunks()

	// will require an extra script tag for runtime.js
	// but, you probably want this, unless you're building a single-page app
	.enableSingleRuntimeChunk()

	/*
	 * FEATURE CONFIG
	 *
	 * Enable & configure other features below. For a full
	 * list of features, see:
	 * https://symfony.com/doc/current/frontend.html#adding-more-features
	 */
	.cleanupOutputBeforeBuild()

	// Displays build status system notifications to the user
	// .enableBuildNotifications()

	.enableSourceMaps(!Encore.isProduction())
	// enables hashed filenames (e.g. app.abc123.css)
	.enableVersioning(Encore.isProduction())

	// configure Babel
	// .configureBabel((config) => {
	//     config.plugins.push('@babel/a-babel-plugin');
	// })

	// enables and configure @babel/preset-env polyfills
	.configureBabelPresetEnv((config) => {
		config.useBuiltIns = 'usage'
		config.corejs = '3.38'
	})

	// enables CSS modules support
	.configureCssLoader((options) => {
		options.modules = {
			auto: (resourcePath) =>
				resourcePath.endsWith('.module.css') ||
				resourcePath.endsWith('.module.scss') ||
				resourcePath.endsWith('.module.sass'),
			localIdentName: '[name]__[local]___[hash:base64:5]',
			namedExport: false,
		}
	})

	// enables Sass/SCSS support
	.enableSassLoader()

	// enables PostCss support
	.enablePostCssLoader()

	// Enable Stimulus bridge
	.enableStimulusBridge('./assets/controllers.json')

	// uncomment if you use TypeScript
	.enableTypeScriptLoader()

	// uncomment if you use React
	.enableReactPreset()

	.addAliases({
		'@': path.resolve(__dirname, 'assets/react'),
		// '@shared': path.resolve(__dirname, 'assets/react/shared'),
		// "@islands": path.resolve(__dirname, "assets/react/islands"),
		'@ui': path.resolve(__dirname, 'assets/react/shared/ui'),
		'@hooks': path.resolve(__dirname, 'assets/react/shared/hooks'),
		'@utils': path.resolve(__dirname, 'assets/react/shared/utils'),
		'@config': path.resolve(__dirname, 'assets/react/shared/config'),
		'@pages': path.resolve(__dirname, 'assets/react/shared/pages'),
		'@components': path.resolve(__dirname, 'assets/react/shared/components'),
		'@api': path.resolve(__dirname, 'assets/react/shared/api'),
	})

// uncomment to get integrity="..." attributes on your script & link tags
// requires WebpackEncoreBundle 1.4 or higher
//.enableIntegrityHashes(Encore.isProduction())

// uncomment if you're having problems with a jQuery plugin
//.autoProvidejQuery()

module.exports = Encore.getWebpackConfig()
