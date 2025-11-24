import { Component, inject } from '@angular/core';
import { AsyncPipe, DatePipe } from '@angular/common';
import { CourseService } from '../../services/course.service';
import { TranslateModule } from '@ngx-translate/core';

@Component({
    selector: 'app-dashboard',
    imports: [AsyncPipe, DatePipe, TranslateModule],
    templateUrl: './dashboard.html'
})
export class DashboardComponent {
    private courseService = inject(CourseService);
    courses$ = this.courseService.getCourses();
}
