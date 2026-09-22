import CaseController from './CaseController'
import SoapController from './SoapController'
import SubmissionController from './SubmissionController'
import PortfolioController from './PortfolioController'
const Student = {
    CaseController: Object.assign(CaseController, CaseController),
SoapController: Object.assign(SoapController, SoapController),
SubmissionController: Object.assign(SubmissionController, SubmissionController),
PortfolioController: Object.assign(PortfolioController, PortfolioController),
}

export default Student