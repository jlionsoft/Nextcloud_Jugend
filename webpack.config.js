const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: path.resolve(__dirname, 'src/main.ts'),
	settings: path.resolve(__dirname, 'src/settings.ts'),
}
webpackConfig.output.filename = '[name].js'
webpackConfig.output.chunkFilename = 'chunks/[name]-[contenthash].js'
webpackConfig.resolve.extensions = ['.ts', '.js', '.vue', '.json']

module.exports = webpackConfig
