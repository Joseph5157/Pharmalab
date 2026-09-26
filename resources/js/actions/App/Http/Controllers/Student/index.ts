import CaseController from './CaseController'
import CaseContextController from './CaseContextController'
import CaseClinicalProfileController from './CaseClinicalProfileController'
import CaseEditorController from './CaseEditorController'
import CaseVitalController from './CaseVitalController'
import SoapController from './SoapController'
import SubmissionController from './SubmissionController'
import PortfolioController from './PortfolioController'
const Student = {
    CaseController: Object.assign(CaseController, CaseController),
CaseContextController: Object.assign(CaseContextController, CaseContextController),
CaseClinicalProfileController: Object.assign(CaseClinicalProfileController, CaseClinicalProfileController),
CaseEditorController: Object.assign(CaseEditorController, CaseEditorController),
CaseVitalController: Object.assign(CaseVitalController, CaseVitalController),
SoapController: Object.assign(SoapController, SoapController),
SubmissionController: Object.assign(SubmissionController, SubmissionController),
PortfolioController: Object.assign(PortfolioController, PortfolioController),
}

export default Student