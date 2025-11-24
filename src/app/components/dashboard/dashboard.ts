import { Component, inject } from '@angular/core';
import { AsyncPipe, DatePipe } from '@angular/common';
import { LearningService } from '../../services/learning.service';
import { TranslateModule } from '@ngx-translate/core';
import { RouterLink } from '@angular/router';

@Component({
    selector: 'app-dashboard',
    imports: [AsyncPipe, DatePipe, TranslateModule, RouterLink],
    templateUrl: './dashboard.html'
})
export class DashboardComponent {
    private learningService = inject(LearningService);
    courses$ = this.learningService.getMyCourses();
}
