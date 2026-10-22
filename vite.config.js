import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig({
	main: 'src/main.js',
}, {
	// one CSS file per entry in css/, loaded via Util::addStyle in PageController
	extractLicenseInformation: false,
})
