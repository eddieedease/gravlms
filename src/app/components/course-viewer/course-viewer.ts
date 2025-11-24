import { Component, inject, signal, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { LearningService } from '../../services/learning.service';
import { CourseService } from '../../services/course.service';
import { TranslateModule } from '@ngx-translate/core';
import { MarkedPipe } from '../../pipes/marked.pipe';
import { toSignal } from '@angular/core/rxjs-interop';
import { map, switchMap, tap } from 'rxjs/operators';


@Component({
    selector: 'app-course-viewer',
    imports: [CommonModule, TranslateModule, MarkedPipe],
    templateUrl: './course-viewer.html'
})
export class CourseViewerComponent {
    private route = inject(ActivatedRoute);
    private learningService = inject(LearningService);
    private courseService = inject(CourseService);

    courseId = toSignal(this.route.paramMap.pipe(map(params => Number(params.get('courseId')))));

    // Fetch course details (pages)
    // In a real app we might want a specific endpoint for "my course details" that includes progress
    // For now we'll fetch pages and progress separately

    pages$ = this.route.paramMap.pipe(
        switchMap(params => this.courseService.getPages().pipe(
            map(pages => pages.filter(p => p.course_id == params.get('courseId')).sort((a, b) => a.display_order - b.display_order))
        ))
    );

    progress$ = this.route.paramMap.pipe(
        switchMap(params => this.learningService.getCourseProgress(Number(params.get('courseId'))))
    );

    selectedPage = signal<any>(null);
    completedPageIds = signal<number[]>([]);

    constructor() {
        // When progress loads, update completedPageIds
        this.progress$.subscribe(progress => {
            if (progress && progress.completed_page_ids) {
                this.completedPageIds.set(progress.completed_page_ids);
            }
        });
    }

    selectPage(page: any) {
        this.selectedPage.set(page);
    }

    completeLesson(pageId: number) {
        const cid = this.courseId();
        if (cid) {
            this.learningService.completeLesson(cid, pageId).subscribe(res => {
                this.completedPageIds.update(ids => [...ids, pageId]);
                // Optionally refresh progress or show success message
            });
        }
    }

    isCompleted(pageId: number) {
        return this.completedPageIds().includes(pageId);
    }
}
