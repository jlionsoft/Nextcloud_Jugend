const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: path.resolve(__dirname, 'src/main.js'),
	groups: path.resolve(__dirname, 'src/groups.js'),
	organization: path.resolve(__dirname, 'src/organization.js'),
	settings: path.resolve(__dirname, 'src/settings.js'),
}
webpackConfig.output.filename = '[name].js'
webpackConfig.output.chunkFilename = 'chunks/[name]-[contenthash].js'

module.exports = webpackConfig
