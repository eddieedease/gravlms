import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { TranslateModule } from '@ngx-translate/core';
import { ApiService } from '../../services/api.service';
import { ConfigService } from '../../services/config.service';

@Component({
    selector: 'app-assessor-dashboard',
    standalone: true,
    imports: [CommonModule, TranslateModule],
    templateUrl: './assessor-dashboard.html'
})
export class AssessorDashboardComponent implements OnInit {
    submissions = signal<any[]>([]);
    loading = signal(true);
    activeStatus = signal<'pending' | 'graded'>('pending');

    constructor(private apiService: ApiService, private config: ConfigService) { }

    ngOnInit() {
        this.loadSubmissions();
    }

    loadSubmissions() {
        this.loading.set(true);
        this.apiService.getAssessments(this.activeStatus()).subscribe({
            next: (data) => {
                this.submissions.set(data);
                this.loading.set(false);
            },
            error: () => this.loading.set(false)
        });
    }

    setTab(status: 'pending' | 'graded') {
        this.activeStatus.set(status);
        this.loadSubmissions();
    }

    grade(submissionId: number, status: 'passed' | 'failed', feedback: string) {
        if (!feedback && status === 'failed') {
            if (!confirm('Mark as failed without feedback?')) return;
        }

        this.apiService.gradeAssessment(submissionId, status, feedback).subscribe({
            next: () => {
                alert(`Submission marked as ${status}`);
                this.loadSubmissions(); // Refresh list
            },
            error: (err) => alert(err.error?.error || 'Failed to grade')
        });
    }

    getFileUrl(path: string): string {
        if (!path) return '';
        if (path.startsWith('http')) return path;

        // Ensure path starts with /
        if (!path.startsWith('/')) {
            path = '/' + path;
        }

        // If path starts with /uploads/, append to apiUrl directly (result: /api/uploads/main/...)
        // Backend route matches /api/uploads/{filename}
        if (path.startsWith('/uploads/')) {
            return `${this.config.apiUrl}${path}`;
        }

        return `${this.config.apiUrl}/uploads${path}`;
    }
}
