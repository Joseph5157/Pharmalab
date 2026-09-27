import CaseController from './CaseController'
import CaseContextController from './CaseContextController'
import CaseClinicalProfileController from './CaseClinicalProfileController'
import CaseEditorController from './CaseEditorController'
import CaseVitalController from './CaseVitalController'
import CaseInvestigationController from './CaseInvestigationController'
import CaseMedicationController from './CaseMedicationController'
import CaseClinicalActivityController from './CaseClinicalActivityController'
import SoapController from './SoapController'
import SubmissionReviewController from './SubmissionReviewController'
import SubmissionController from './SubmissionController'
import PortfolioController from './PortfolioController'
const Student = {
    CaseController: Object.assign(CaseController, CaseController),
CaseContextController: Object.assign(CaseContextController, CaseContextController),
CaseClinicalProfileController: Object.assign(CaseClinicalProfileController, CaseClinicalProfileController),
CaseEditorController: Object.assign(CaseEditorController, CaseEditorController),
CaseVitalController: Object.assign(CaseVitalController, CaseVitalController),
CaseInvestigationController: Object.assign(CaseInvestigationController, CaseInvestigationController),
CaseMedicationController: Object.assign(CaseMedicationController, CaseMedicationController),
CaseClinicalActivityController: Object.assign(CaseClinicalActivityController, CaseClinicalActivityController),
SoapController: Object.assign(SoapController, SoapController),
SubmissionReviewController: Object.assign(SubmissionReviewController, SubmissionReviewController),
SubmissionController: Object.assign(SubmissionController, SubmissionController),
PortfolioController: Object.assign(PortfolioController, PortfolioController),
}

export default Student