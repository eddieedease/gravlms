import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { jsPDF } from 'jspdf';
import { AuthService } from '../../services/auth.service';
import { ConfigService } from '../../services/config.service';
import { FormsModule } from '@angular/forms';

interface PortfolioData {
  user: {
    username: string;
    email: string;
  };
  completed_courses: any[];
  test_results: any[];
  assignment_history: any[];
}

@Component({
  selector: 'app-portfolio',
  standalone: true,
  imports: [CommonModule, TranslateModule, FormsModule],
  templateUrl: './portfolio.html',
  styleUrls: ['./portfolio.css']
})
export class PortfolioComponent implements OnInit {
  portfolioData: PortfolioData | null = null;
  loading = true;
  error = '';
  selectedAssignment: any = null;

  // Pagination and Search State
  pageSize = 5;

  courseSearch = '';
  coursePage = 1;

  assignmentSearch = '';
  assignmentPage = 1;

  constructor(
    private http: HttpClient,
    private translate: TranslateService,
    private authService: AuthService,
    private config: ConfigService,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit() {
    this.fetchPortfolio();
  }

  fetchPortfolio() {
    this.loading = true;
    this.http.get<PortfolioData>(`${this.config.apiUrl}/portfolio`).subscribe({
      next: (data) => {
        this.portfolioData = data;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error fetching portfolio:', err);
        // Ensure we handle non-JSON responses or 404s gracefully
        if (err.status === 404) {
          this.error = 'Portfolio data not found.';
        } else {
          this.error = 'Failed to load portfolio data. Please try again later.';
        }
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  // Course Helpers
  get filteredCourses() {
    if (!this.portfolioData?.completed_courses) return [];
    if (!this.courseSearch) return this.portfolioData.completed_courses;
    const query = this.courseSearch.toLowerCase();
    return this.portfolioData.completed_courses.filter(c =>
      c.title.toLowerCase().includes(query) ||
      (c.description && c.description.toLowerCase().includes(query))
    );
  }

  get paginatedCourses() {
    const start = (this.coursePage - 1) * this.pageSize;
    return this.filteredCourses.slice(start, start + this.pageSize);
  }

  get totalCoursePages() {
    return Math.ceil(this.filteredCourses.length / this.pageSize);
  }

  // Assignment Helpers
  get filteredAssignments() {
    if (!this.portfolioData?.assignment_history) return [];
    if (!this.assignmentSearch) return this.portfolioData.assignment_history;
    const query = this.assignmentSearch.toLowerCase();
    return this.portfolioData.assignment_history.filter(a =>
      a.lesson_title.toLowerCase().includes(query) ||
      a.course_title.toLowerCase().includes(query)
    );
  }

  get paginatedAssignments() {
    const start = (this.assignmentPage - 1) * this.pageSize;
    return this.filteredAssignments.slice(start, start + this.pageSize);
  }

  get totalAssignmentPages() {
    return Math.ceil(this.filteredAssignments.length / this.pageSize);
  }

  // Pagination Controls
  nextCoursePage() {
    if (this.coursePage < this.totalCoursePages) this.coursePage++;
  }

  prevCoursePage() {
    if (this.coursePage > 1) this.coursePage--;
  }

  nextAssignmentPage() {
    if (this.assignmentPage < this.totalAssignmentPages) this.assignmentPage++;
  }

  prevAssignmentPage() {
    if (this.assignmentPage > 1) this.assignmentPage--;
  }

  getCompletedCourse(courseId: any): any {
    if (!this.portfolioData || !courseId) return null;
    return this.portfolioData.completed_courses.find(c => c.id === courseId);
  }

  downloadCertificate(course: any) {
    const doc = new jsPDF({
      orientation: 'landscape',
      unit: 'mm',
      format: 'a4'
    });

    // Background border
    doc.setLineWidth(2);
    doc.setDrawColor(20, 184, 166); // Teal-500
    doc.rect(10, 10, 277, 190);

    // Header
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(40);
    doc.setTextColor(20, 184, 166);
    doc.text('CERTIFICATE', 148.5, 40, { align: 'center' });

    doc.setFontSize(20);
    doc.setTextColor(100, 100, 100);
    doc.text('OF COMPLETION', 148.5, 55, { align: 'center' });

    // Body
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(16);
    doc.setTextColor(0, 0, 0);
    doc.text('This is to certify that', 148.5, 80, { align: 'center' });

    doc.setFont('times', 'italic');
    doc.setFontSize(30);
    doc.text(this.portfolioData?.user.username || 'Student', 148.5, 100, { align: 'center' });

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(16);
    doc.text('has successfully completed the course', 148.5, 120, { align: 'center' });

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(24);
    doc.text(course.title, 148.5, 140, { align: 'center' });

    // Date
    const date = new Date(course.completed_at).toLocaleDateString();
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(14);
    doc.text(`Completed on: ${date}`, 148.5, 160, { align: 'center' });

    // Footer
    doc.setFontSize(10);
    doc.setTextColor(150, 150, 150);
    doc.text('GravLMS Certificate System', 148.5, 185, { align: 'center' });

    doc.save(`certificate-${course.title.replace(/\s+/g, '-').toLowerCase()}.pdf`);
  }

  getImageUrl(url: string | null): string {
    if (!url) return '';
    if (url.startsWith('http')) return url;
    return `${this.config.apiUrl}/uploads/${url}`;
  }

  getFileUrl(path: string): string {
    if (!path) return '';
    if (path.startsWith('http')) return path;

    // Ensure path starts with /
    if (!path.startsWith('/')) {
      path = '/' + path;
    }

    // Assessment file_url often starts with /uploads/ (e.g. /uploads/main/1/file.png)
    // Backend route is /api/uploads/{filename}
    // If we append /uploads/main/... to /api, we get /api/uploads/main/...
    // This matches the route correctly (filename = main/...)

    // Check if path already starts with /uploads/
    if (path.startsWith('/uploads/')) {
      return `${this.config.apiUrl}${path}`;
    }

    // Otherwise assume it's a relative filename and needs /uploads/ prefix
    return `${this.config.apiUrl}/uploads${path}`;
  }

  openModal(assignment: any) {
    this.selectedAssignment = assignment;
  }

  closeModal() {
    this.selectedAssignment = null;
  }
}
