import DashboardController from './DashboardController'
import CaseDraftNoteController from './CaseDraftNoteController'
import Student from './Student'
import Faculty from './Faculty'
import Admin from './Admin'
import Settings from './Settings'
const Controllers = {
    DashboardController: Object.assign(DashboardController, DashboardController),
CaseDraftNoteController: Object.assign(CaseDraftNoteController, CaseDraftNoteController),
Student: Object.assign(Student, Student),
Faculty: Object.assign(Faculty, Faculty),
Admin: Object.assign(Admin, Admin),
Settings: Object.assign(Settings, Settings),
}

export default Controllers