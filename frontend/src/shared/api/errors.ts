// Single parse path for RFC 9457 Problem Details (application/problem+json).
// Every 4xx/5xx becomes an AppError carrying the structured problem.

export interface ValidationErrorItem {
  field: string;
  message: string;
  code: string;
}

export interface ProblemDetails {
  type: string;
  title: string;
  status: number;
  detail?: string;
  instance?: string;
  errors?: ValidationErrorItem[];
}

export class AppError extends Error {
  constructor(
    public readonly status: number,
    public readonly problem: ProblemDetails | null,
  ) {
    super(problem?.title ?? `Request failed with status ${String(status)}`);
    this.name = 'AppError';
  }

  /** Network errors and 5xx are retryable; 4xx are not. */
  get isRetryable(): boolean {
    return this.status === 0 || this.status >= 500;
  }

  /** The problem-type slug (last path segment of the type URI), for message mapping. */
  get problemSlug(): string | null {
    if (this.problem === null) {
      return null;
    }
    const parts = this.problem.type.split('/');
    return parts[parts.length - 1] ?? null;
  }
}

export async function parseProblemDetails(response: Response): Promise<AppError> {
  let problem: ProblemDetails | null = null;
  try {
    const data: unknown = await response.json();
    if (typeof data === 'object' && data !== null && 'type' in data && 'title' in data) {
      problem = data as ProblemDetails;
    }
  } catch {
    problem = null;
  }
  return new AppError(response.status, problem);
}
