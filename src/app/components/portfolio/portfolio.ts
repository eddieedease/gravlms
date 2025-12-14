import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { jsPDF } from 'jspdf';
import { AuthService } from '../../services/auth.service';
import { ConfigService } from '../../services/config.service';

interface PortfolioData {
  user: {
    username: string;
    email: string;
  };
  completed_courses: any[];
  test_results: any[];
}

@Component({
  selector: 'app-portfolio',
  standalone: true,
  imports: [CommonModule, TranslateModule],
  templateUrl: './portfolio.html',
  styleUrls: ['./portfolio.css']
})
export class PortfolioComponent implements OnInit {
  portfolioData: PortfolioData | null = null;
  loading = true;
  error = '';

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
}
