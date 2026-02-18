import initLicenseWizard from "./wizard";
import initAddons from "./add-ons/add-ons";


const initLicenseManager = () => {
    initLicenseWizard()
    initAddons()
}
initLicenseManager()