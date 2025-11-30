import { Component, inject, OnInit, signal, computed, ElementRef, viewChild } from '@angular/core';
import { CourseService } from '../../services/course.service';
import { AuthService } from '../../services/auth.service';
import { ApiService } from '../../services/api.service';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { marked } from 'marked';
import { TestEditorComponent } from '../test-editor/test-editor';

@Component({
  selector: 'app-editor',
  imports: [ReactiveFormsModule, TestEditorComponent],
  templateUrl: './editor.html',
  styleUrl: './editor.css',
})
export class Editor implements OnInit {
  private courseService = inject(CourseService);
  private authService = inject(AuthService);
  private http = inject(HttpClient);
  private apiService = inject(ApiService);
  private fb = inject(FormBuilder);

  fileInput = viewChild<ElementRef<HTMLInputElement>>('fileInput');
  uploadStatus = signal<string>('');

  courses = signal<any[]>([]);
  selectedCourse = signal<any>(null);

  pages = signal<any[]>([]);
  // Filter pages by selected course
  coursePages = computed(() => {
    if (!this.selectedCourse()) return [];
    return this.pages()
      .filter(p => p.course_id === this.selectedCourse().id)
      .sort((a, b) => a.display_order - b.display_order);
  });

  selectedPage = signal<any>(null);
  previewHtml = signal<string>('');

  ltiTools = signal<any[]>([]);
  showLtiSelector = signal(false);

  form = this.fb.group({
    title: ['', Validators.required],
    content: [''],
    type: ['page'],
    course_id: [null as number | null]
  });

  ngOnInit() {
    this.loadCourses();
    this.loadPages();
    this.loadLtiTools();

    // Update preview when content changes
    this.form.get('content')?.valueChanges.subscribe(val => {
      this.updatePreview(val || '');
    });
  }

  async updatePreview(content: string) {
    const html = await marked.parse(content);
    this.previewHtml.set(html);
  }

  loadCourses() {
    this.courseService.getCourses().subscribe(courses => {
      this.courses.set(courses);
      // Restore selection if needed
      if (this.selectedCourse()) {
        const updated = courses.find(c => c.id === this.selectedCourse().id);
        this.selectedCourse.set(updated || null);
      }
    });
  }

  loadPages() {
    this.courseService.getPages().subscribe(pages => {
      this.pages.set(pages);
      if (this.selectedPage()) {
        const updated = pages.find(p => p.id === this.selectedPage().id);
        if (updated) {
          this.selectPage(updated);
        } else {
          this.selectedPage.set(null);
        }
      }
    });
  }

  selectCourse(course: any) {
    this.selectedCourse.set(course);
    this.selectedPage.set(null);
  }

  createCourse() {
    const title = prompt('Enter course title:');
    if (title) {
      this.courseService.createCourse({ title }).subscribe(() => {
        this.loadCourses();
      });
    }
  }

  deleteCourse(course: any) {
    if (confirm(`Delete course "${course.title}" and all its pages?`)) {
      this.courseService.deleteCourse(course.id).subscribe(() => {
        this.selectedCourse.set(null);
        this.loadCourses();
        this.loadPages(); // Pages might be deleted by cascade or need refresh
      });
    }
  }

  selectPage(page: any) {
    this.selectedPage.set(page);
    this.form.patchValue({
      title: page.title,
      content: page.content,
      type: page.type || 'page',
      course_id: page.course_id
    });
    this.updatePreview(page.content || '');
  }

  createPage(type: 'page' | 'test' = 'page') {
    if (!this.selectedCourse()) return;
    const newPage = {
      title: type === 'test' ? 'New Test' : 'New Page',
      content: '',
      type: type,
      course_id: this.selectedCourse().id
    };
    this.courseService.createPage(newPage).subscribe(() => {
      this.loadPages();
    });
  }

  savePage() {
    if (this.selectedPage() && this.form.valid) {
      const updatedPage = { ...this.form.value, course_id: this.selectedCourse().id };
      this.courseService.updatePage(this.selectedPage().id, updatedPage).subscribe(() => {
        this.loadPages();
        alert('Saved!');
      });
    }
  }

  deletePage() {
    if (this.selectedPage() && confirm('Are you sure?')) {
      this.courseService.deletePage(this.selectedPage().id).subscribe(() => {
        this.selectedPage.set(null);
        this.loadPages();
      });
    }
  }

  movePage(page: any, direction: 'up' | 'down') {
    const pages = this.coursePages();
    const index = pages.findIndex(p => p.id === page.id);
    if (index === -1) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= pages.length) return;

    const otherPage = pages[newIndex];

    // Swap display_order
    // Ideally this should be done on backend or by swapping order values
    // For simplicity, let's assume display_order is index based or we just swap them
    // We need to update both pages

    // Assign temp orders
    const pageOrder = page.display_order || 0;
    const otherOrder = otherPage.display_order || 0;

    // If orders are same (e.g. 0), we need to fix them first. 
    // But let's just swap their current positions in the array and assign new orders based on index

    // Better approach: re-assign order for all pages in this course based on current array + swap
    const reordered = [...pages];
    [reordered[index], reordered[newIndex]] = [reordered[newIndex], reordered[index]];

    // Update all pages with new order
    reordered.forEach((p, i) => {
      if (p.display_order !== i) {
        this.courseService.updatePage(p.id, { ...p, display_order: i }).subscribe();
      }
    });

    // Optimistic update
    this.loadPages();
  }

  triggerFileInput() {
    this.fileInput()?.nativeElement.click();
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.uploadImage(input.files[0]);
    }
  }

  uploadImage(file: File) {
    // Validate file type
    if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
      this.uploadStatus.set('Error: Only JPG and PNG images are allowed');
      return;
    }

    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
      this.uploadStatus.set('Error: File size must not exceed 5MB');
      return;
    }

    this.uploadStatus.set('Uploading...');

    const formData = new FormData();
    formData.append('image', file);

    const token = this.authService.getToken();
    const headers = new HttpHeaders({
      'Authorization': `Bearer ${token}`
    });

    this.http.post<any>('http://localhost:8080/api/uploads', formData, { headers }).subscribe({
      next: (response) => {
        this.uploadStatus.set('Upload successful!');
        this.insertImageMarkdown(response.filename);
        setTimeout(() => this.uploadStatus.set(''), 3000);
      },
      error: (error) => {
        this.uploadStatus.set('Error: ' + (error.error?.error || 'Upload failed'));
        setTimeout(() => this.uploadStatus.set(''), 5000);
      }
    });
  }

  insertImageMarkdown(filename: string) {
    const imageUrl = `http://localhost:8080/api/uploads/${filename}`;
    const markdown = `![Image](${imageUrl})`;

    const currentContent = this.form.get('content')?.value || '';
    const newContent = currentContent + '\n\n' + markdown;

    this.form.patchValue({ content: newContent });
    this.updatePreview(newContent);
  }

  loadLtiTools() {
    this.apiService.getLtiTools().subscribe(tools => {
      this.ltiTools.set(tools);
    });
  }

  insertLtiTool(tool: any) {
    const markdown = `[lti-tool id="${tool.id}"]`;
    const currentContent = this.form.get('content')?.value || '';
    const newContent = currentContent + '\n\n' + markdown;

    this.form.patchValue({ content: newContent });
    this.updatePreview(newContent);
    this.showLtiSelector.set(false);
  }
}
