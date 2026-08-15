import { apiClient } from '@/lib/api'
import type {
  ConfirmPortfolioItemPayload,
  FreelancerProfile,
  FreelancerSkill,
  PortfolioItem,
  PortfolioUploadUrl,
  UpdateFreelancerProfilePayload,
} from '../types'

/**
 * No routes exist yet for freelancer profile/skills/portfolio management —
 * only /auth/register/freelancer is wired up. These mirror the presign/
 * confirm pattern already used for business documents (RequestUploadUrl /
 * ConfirmUpload) until the real endpoints land.
 */
export async function fetchMyFreelancerProfile(): Promise<FreelancerProfile> {
  const { data } = await apiClient.get<{ data: FreelancerProfile }>('/freelancers/me')
  return data.data
}

export async function updateMyFreelancerProfile(
  payload: UpdateFreelancerProfilePayload,
): Promise<FreelancerProfile> {
  const { data } = await apiClient.patch<{ data: FreelancerProfile }>('/freelancers/me', payload)
  return data.data
}

export async function replaceMySkills(
  skills: { skill_name: string; proficiency_level: string }[],
): Promise<FreelancerSkill[]> {
  const { data } = await apiClient.put<{ data: FreelancerSkill[] }>('/freelancers/me/skills', {
    skills,
  })
  return data.data
}

export async function fetchMyPortfolio(): Promise<PortfolioItem[]> {
  const { data } = await apiClient.get<{ data: PortfolioItem[] }>('/freelancers/me/portfolio')
  return data.data
}

export async function requestPortfolioUploadUrl(filename: string): Promise<PortfolioUploadUrl> {
  const { data } = await apiClient.post<{ data: PortfolioUploadUrl }>(
    '/freelancers/me/portfolio/upload-url',
    { filename },
  )
  return data.data
}

export async function confirmPortfolioItem(
  payload: ConfirmPortfolioItemPayload,
): Promise<PortfolioItem> {
  const { data } = await apiClient.post<{ data: PortfolioItem }>(
    '/freelancers/me/portfolio',
    payload,
  )
  return data.data
}

export async function deletePortfolioItem(id: string): Promise<void> {
  await apiClient.delete(`/freelancers/me/portfolio/${id}`)
}

export async function uploadFileToS3(
  uploadUrl: PortfolioUploadUrl,
  file: File,
  onProgress: (percent: number) => void,
): Promise<void> {
  await new Promise<void>((resolve, reject) => {
    const xhr = new XMLHttpRequest()
    xhr.open('PUT', uploadUrl.url)
    Object.entries(uploadUrl.headers).forEach(([key, value]) => {
      xhr.setRequestHeader(key, value)
    })
    xhr.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100))
      }
    }
    xhr.onload = () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve()
      } else {
        reject(new Error(`Upload failed with status ${xhr.status}`))
      }
    }
    xhr.onerror = () => reject(new Error('Upload failed.'))
    xhr.send(file)
  })
}
