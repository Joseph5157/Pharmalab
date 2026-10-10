import AcademicController from './AcademicController'
import ProgressController from './ProgressController'
import ClinicalSiteController from './ClinicalSiteController'
import PeopleController from './PeopleController'
import RotationController from './RotationController'

const Admin = {
    AcademicController: Object.assign(AcademicController, AcademicController),
    ProgressController: Object.assign(ProgressController, ProgressController),
    ClinicalSiteController: Object.assign(ClinicalSiteController, ClinicalSiteController),
    PeopleController: Object.assign(PeopleController, PeopleController),
    RotationController: Object.assign(RotationController, RotationController),
}

export default Admin